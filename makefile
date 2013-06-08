_FOLDERS = settings repos zips notifications web/cache

.PHONY: all default dev docs check clean folders config submodules

# Useful groupings
default: dev
all: dev docs submodules config

# Actual targets
clean:
	rm -rf $(_FOLDERS) html latex
	rm -f config/automagic.ini
	rm -f srobo-ide.deb metapackages/deb/srobo-ide.deb

dev: folders config lint-reference/sr.py

config: config/automagic.ini

docs:
	doxygen doxyfile

deb: srobo-ide.deb

# Helpers
lint-reference/sr.py: submodules

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
	git submodule init
	git submodule update

srobo-ide.deb: metapackages/deb/srobo-ide.deb
	cp metapackages/deb/srobo-ide.deb .

metapackages/deb/srobo-ide.deb: metapackages/deb/srobo-ide/DEBIAN/control
	dpkg --build metapackages/deb/srobo-ide

check: all
	./run-tests
