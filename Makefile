.PHONY: fmt lint test build verify quality-gates node-version

node-version:
	@node -e 'const [major, minor] = process.versions.node.split(".").map(Number); const ok = (major === 20 && minor >= 19) || (major === 22 && minor >= 12) || major >= 23; if (!ok) { console.error("Node " + process.versions.node + " cannot build this app. Need ^20.19.0 or >=22.12.0. See .nvmrc."); process.exit(1); }'

fmt: node-version
	vendor/bin/pint --test
	npx prettier --check "resources/js/**/*.{ts,vue}" "vite.config.ts"

lint:
	vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
	vendor/bin/deptrac analyse

test: node-version
	php artisan test --compact
	npm run test:run

build: node-version
	npx vue-tsc --noEmit
	npm run build

verify: fmt lint build test

quality-gates: verify
