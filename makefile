_FOLDERS = settings repos zips /tmp/ide-feed-cache notifications

_JAVA_KEYSTORE = applet/.keystore
_JAVA_KEYSTORE_PWD = testpass
_JAVE_KEYSTORE_USER = Test User

.PHONY: all default dev docs clean applet folders config submodules

# Useful groupings
default: dev
all: dev docs applet submodules config

applet: applet/build.xml applet/.keystore
	cd applet/ && ant build

# Actual targets
clean:
	rm -rf $(_FOLDERS) html latex
	rm -f config/automagic.ini
	cd applet/ && ant clean
	rm -f srobo-ide.deb metapackages/deb/srobo-ide.deb

dev: applet folders config lint-reference/sr.py

config: config/automagic.ini

docs:
	doxygen doxyfile

package: srobo-ide.deb

# Helpers
lint-reference/sr.py: submodules

config/automagic.ini:
	/bin/echo -n "pylint.path = " > $@
	which pylint >> $@

applet/.keystore:
	keytool -genkeypair -keyalg rsa -alias test-only-applet-key \
	-storepass $(_JAVA_KEYSTORE_PWD) -keypass $(_JAVA_KEYSTORE_PWD) \
	-dname "cn=$(_JAVA_KEYSTORE_USER), ou=SR, o=SR, c=UK" -keystore $(_JAVA_KEYSTORE)

folders: $(_FOLDERS)

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
