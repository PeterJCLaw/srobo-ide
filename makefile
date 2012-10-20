_FOLDERS = settings repos zips notifications

_JAVA_KEYSTORE = applet/.keystore
_JAVA_KEYSTORE_PWD = testpass
_JAVE_KEYSTORE_USER = Test User

.PHONY: all default dev docs check clean applet folders config submodules

# Useful groupings
default: dev
all: dev docs submodules config applet

applet: applet/build.xml applet/.keystore
	cd applet/ && ant build

# Actual targets
clean:
	rm -rf $(_FOLDERS) html latex
	rm -f config/automagic.ini
	cd applet/ && ant clean
	rm -f srobo-ide.deb metapackages/deb/srobo-ide.deb

dev: folders config lint-reference/sr.py applet

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

applet/.keystore:
	keytool -genkeypair -keyalg rsa -alias test-only-applet-key \
	-storepass $(_JAVA_KEYSTORE_PWD) -keypass $(_JAVA_KEYSTORE_PWD) \
	-dname "cn=$(_JAVA_KEYSTORE_USER), ou=SR, o=SR, c=UK" -keystore $(_JAVA_KEYSTORE)

zips/.htaccess:
	ln resources/zips-htaccess zips/.htaccess

zip-htaccess: zips/.htaccess

folders: $(_FOLDERS) zip-htaccess

$(_FOLDERS):
	mkdir -p -m 777 $@

submodules:
	git submodule init
	git submodule update

sign: applet
	jarsigner applet/build/checkout.jar prod-key

srobo-ide.deb: metapackages/deb/srobo-ide.deb
	cp metapackages/deb/srobo-ide.deb .

metapackages/deb/srobo-ide.deb: metapackages/deb/srobo-ide/DEBIAN/control
	dpkg --build metapackages/deb/srobo-ide

check: all
	./run-tests
