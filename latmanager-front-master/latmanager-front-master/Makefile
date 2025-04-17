.PHONY: commit version bump-patch bump-minor bump-major console

VERSION_FILE := .version
CURRENT_VERSION := $(shell if exist $(VERSION_FILE) (type $(VERSION_FILE)) else (echo 0.0.0))

version:
	@echo Version actuelle: $(CURRENT_VERSION)

define update_version
	@echo $(1) > $(VERSION_FILE)
	@echo Version mise a jour: $(1)
endef

bump-patch:
	$(eval NEW_VERSION := $(shell powershell -Command "$$v='$(CURRENT_VERSION)'.Split('.'); $$v[2]=[int]$$v[2]+1; $$v -join '.'"))
	$(call update_version,$(NEW_VERSION))

bump-minor:
	$(eval NEW_VERSION := $(shell powershell -Command "$$v='$(CURRENT_VERSION)'.Split('.'); $$v[1]=[int]$$v[1]+1; $$v[2]=0; $$v -join '.'"))
	$(call update_version,$(NEW_VERSION))

bump-major:
	$(eval NEW_VERSION := $(shell powershell -Command "$$v='$(CURRENT_VERSION)'.Split('.'); $$v[0]=[int]$$v[0]+1; $$v[1]=0; $$v[2]=0; $$v -join '.'"))
	$(call update_version,$(NEW_VERSION))

commit:
	@if "$(message)" == "" ( \
		echo Usage: make commit message="Votre message de commit" && \
		exit 1 \
	)
	@if not exist $(VERSION_FILE) ( \
		echo 0.0.0 > $(VERSION_FILE) \
	)
	@echo. >> CHANGELOG.md
	@echo ## [$(CURRENT_VERSION)] - %date:~-4,4%-%date:~-7,2%-%date:~-10,2% >> CHANGELOG.md
	@echo - $(message) >> CHANGELOG.md
	git add .
	git commit -m "[v$(CURRENT_VERSION)] $(message)"
	git push
	git push --tags
	@echo Commit et push effectues avec le message: [v$(CURRENT_VERSION)] $(message)
	@echo Changelog mis a jour

console:
	@if not exist $(VERSION_FILE) ( \
		echo 0.0.0 > $(VERSION_FILE) \
	)
	@powershell -NoProfile -ExecutionPolicy Bypass -File menu.ps1 