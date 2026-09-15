.PHONY: help setup build up down restart logs php node migrate fresh artisan composer test \
	android android-shell android-install android-sync android-debug android-release \
	android-cache-clean

help:
	@echo ""
	@echo "  make setup      First-time project setup"
	@echo "  make build      Build all services"
	@echo "  make up         Start all services"
	@echo "  make down       Stop all services"
	@echo "  make restart    Restart all services"
	@echo "  make logs       Tail all logs"
	@echo "  make php        Shell into PHP container"
	@echo "  make node       Shell into Node container"
	@echo "  make migrate    Run migrations"
	@echo "  make fresh      Fresh migrate + seed"
	@echo "  make test       Run the backend test suite"
	@echo "  make artisan    Run artisan (make artisan CMD='route:list')"
	@echo "  make composer   Run composer (make composer CMD='require ...')"
	@echo ""
	@echo "  Android app — the Capacitor WebView build, this is what ships to Play"
	@echo "  make android-install   npm install in src-capacitor (Gradle needs it)"
	@echo "  make android-sync      Copy config + web assets into the Android project"
	@echo "  make android-debug     Build the debug APK to sideload"
	@echo "  make android-release   Build the signed AAB + APK for Play (needs the keystore)"
	@echo "  make android CMD='...' Run any command in the Capacitor container"
	@echo "  make android-shell     Shell into the Capacitor toolchain container"
	@echo "  make android-cache-clean  Fix a corrupted Gradle cache (see the target)"
	@echo ""

setup:
	@chmod +x setup.sh && ./setup.sh

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

php:
	docker compose exec php bash

node:
	docker compose exec node bash

migrate:
	docker compose exec php php artisan migrate

fresh:
	docker compose exec php php artisan migrate:fresh --seed

# Deliberately serial. PHPUnit's parallel runner defaults to one worker per core, which on a
# 16-core desktop is enough concurrent postgres connections and PHP processes to push the
# machine into swap; this suite is small enough that it is not worth the risk.
test:
	docker compose exec php php artisan test

artisan:
	docker compose exec php php artisan $(CMD)

composer:
	docker compose exec php composer $(CMD)

# The Capacitor build — the WebView app, and the build that ships to Play. The toolchain is
# a one-shot builder behind a compose profile, so these use `run --rm` rather than `exec`:
# there is no long-running container to attach to, and every invocation gets the same pinned
# JDK and SDK whatever the host has installed.
#
# `front/src-capacitor` is mounted at /work, so everything below acts on the generated
# Android project in place. Order for a first build from a clean checkout:
#
#   make android-install   # once, and after any dependency change
#   make android-sync      # after any edit to capacitor.config.json
#   make android-debug     # the APK to sideload
#   make android-release   # the signed AAB for Play
CAP_RUN = USER_ID=$(shell id -u) GROUP_ID=$(shell id -g) \
	docker compose --profile capacitor run --rm capacitor

android:
	$(CAP_RUN) $(CMD)

android-shell:
	$(CAP_RUN) bash

# Gradle cannot even configure the project without this: `capacitor.settings.gradle` declares
# the plugin subprojects as directories inside ../node_modules, so a missing install fails at
# settings evaluation with a path error rather than anything mentioning npm.
android-install:
	$(CAP_RUN) npm install
	@echo "Installed. Next: make android-sync"

# `cap sync` copies capacitor.config.json into app/src/main/assets/, which is where the app
# reads `server.url` from at runtime — so an edit to that file does nothing until this runs.
#
# It also copies `webDir` into the APK, and refuses to run when that directory is missing. The
# app loads the live site (server.url), so those assets are never rendered; a placeholder is
# enough to keep the copy step happy, and is created rather than assumed because
# src-capacitor/www is gitignored.
android-sync:
	$(CAP_RUN) sh -c 'mkdir -p www \
		&& [ -f www/index.html ] || echo "<!doctype html><title>Dates</title>" > www/index.html; \
		exec npx cap sync android'

# Signs with the Android debug key from docker/volumes/android_home, which is bind-mounted so
# the fingerprint stays the same across builds — that fingerprint is what
# /.well-known/assetlinks.json publishes. No secrets needed, and not installable on Play.
#
# `sh -c` with an explicit cd rather than a bare `./gradlew`: a relative program name is
# resolved by runc against the container's cwd, and the failure when it cannot be reads like a
# broken image rather than a missing file.
android-debug:
	$(CAP_RUN) sh -c 'cd /work/android && exec ./gradlew --no-daemon assembleDebug'
	@echo "APK: front/src-capacitor/android/app/build/outputs/apk/debug/app-debug.apk"
	@echo "Install: adb install -r front/src-capacitor/android/app/build/outputs/apk/debug/app-debug.apk"

# Gradle's transform cache lives in the shared `capacitor_gradle` volume, and a build killed
# part-way through writing it leaves an entry Gradle can neither read nor replace by itself:
#
#   Could not read workspace metadata from /home/app/.gradle/caches/<version>/transforms/<hash>/metadata.bin
#
# That is the whole failure. The directory is a cache, so deleting it costs one slower build and
# nothing else — the fix is not `--rerun-tasks`, a clean checkout, or reinstalling anything.
#
# It is provoked by interrupting a build (Ctrl-C, a timeout, a killed container) or by running two
# builds against the volume at once, so the way not to meet it is to let a build finish.
android-cache-clean:
	$(CAP_RUN) sh -c 'rm -rf /home/app/.gradle/caches/*/transforms'
	@echo "Transform cache cleared. The next build re-creates it and will be slower once."

# The upload key is the app's permanent identity on Play — lose it and this app can never be
# updated again by anyone — so it lives outside the repo and is bind-mounted read-only for the
# one command that needs it.
#
# Override for a key kept elsewhere: `make android-release KEYSTORE=/path/to/upload.jks`.
KEYSTORE ?= $(HOME)/keys/dates/upload.jks

# The AAB that goes to Play — signed with the upload key, not the debug key.
#
# `android-sync` first, not as a convenience: `cap sync` is what copies capacitor.config.json
# into the assets the app reads `server.url` from, so a release built without it ships whatever
# config was there at the last sync.
#
# `assembleRelease` alongside `bundleRelease` on purpose: Play takes the .aab, but an .aab cannot
# be installed on a phone, and the APK from the same run carries the same signature — so the thing
# you sideload to check before uploading is the thing you upload.
#
# The passwords come from the environment so they stay out of the shell history and out of git:
#   ANDROID_KEYSTORE_PASSWORD=... ANDROID_KEY_PASSWORD=... make android-release
android-release: android-sync
	@test -f "$(KEYSTORE)" || { \
		echo "No upload keystore at $(KEYSTORE)."; \
		echo "Create it once (docs/play_console.md) or pass KEYSTORE=<path>."; \
		exit 1; \
	}
	@test -n "$$ANDROID_KEYSTORE_PASSWORD" || { \
		echo "ANDROID_KEYSTORE_PASSWORD is not set — gradle would sign with an empty password"; \
		echo "and fail deep inside the signing task rather than here."; \
		exit 1; \
	}
	USER_ID=$(shell id -u) GROUP_ID=$(shell id -g) \
		docker compose --profile capacitor run --rm \
		-e ANDROID_KEYSTORE_PASSWORD -e ANDROID_KEY_PASSWORD -e ANDROID_KEY_ALIAS \
		-v "$(KEYSTORE):/keys/upload.jks:ro" \
		capacitor sh -c 'cd /work/android && exec ./gradlew --no-daemon bundleRelease assembleRelease'
	@echo "AAB: front/src-capacitor/android/app/build/outputs/bundle/release/app-release.aab"
	@echo "APK: front/src-capacitor/android/app/build/outputs/apk/release/app-release.apk (same key; sideload this to test before uploading)"
