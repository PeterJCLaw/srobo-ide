_FOLDERS = settings repos zips notifications web/cache

.PHONY: all default dev docs check clean folders config submodules lint-venv-config

# Useful groupings
default: dev
all: dev docs submodules config

# Actual targets
clean:
	rm -rf $(_FOLDERS) lint-venv html latex
	rm -f config/automagic.ini
	rm -f srobo-ide.deb metapackages/deb/srobo-ide.deb

dev: folders config lint-reference/sr.py

config: config/automagic.ini

docs:
	doxygen doxyfile

deb: srobo-ide.deb

# Helpers
lint-reference/sr.py: submodules

lint-venv:
	virtualenv $@
	$@/bin/pip install -r pylint-requirements.txt

lint-venv-config: lint-venv
	/bin/echo 'pylint.path = "${PWD}/lint-venv/bin/pylint"' > config/automagic.ini
	/bin/echo 'python.path = "${PWD}/lint-venv/bin/python"' >> config/automagic.ini

config/automagic.ini:
	/bin/echo -n "pylint.path = " > $@
	which pylint >> $@
	/bin/echo -n "python.path = " >> $@
	which python >> $@

zips/.htaccess:
	ln resources/zips-htaccess zips/.htaccess

zip-htaccess: zips/.htaccess

folders: $(_FOLDERS) zip-htaccess

$(_FOLDERS):
	mkdir -p -m 777 $@

submodules:
	git submodule update --init --recursive

srobo-ide.deb: metapackages/deb/srobo-ide.deb
	cp metapackages/deb/srobo-ide.deb .

metapackages/deb/srobo-ide.deb: metapackages/deb/srobo-ide/DEBIAN/control
	dpkg --build metapackages/deb/srobo-ide

check: all
	./run-tests
