_REPOS = repos/1 repos/2
_ZIPS = zips/1 zips/2
_FOLDERS = settings $(_REPOS) $(_ZIPS) /tmp/ide-feed-cache notifications

_JAVA_KEYSTORE = applet/.keystore
_JAVA_KEYSTORE_PWD = testpass

.PHONY: all default dev docs clean applet folders config

# Useful groupings
default: dev
all: dev docs applet config

applet: applet/build.xml applet/.keystore
	cd applet/ && ant build

# Actual targets
clean:
	rm -rf $(_FOLDERS) repos html latex zips
	rm -f applet/.keystore
	cd applet/ && ant clean

dev: applet folders config

config: config/automagic.ini

docs:
	doxygen doxyfile

# Helpers
config/automagic.ini:
	echo -n "pylint_path = " > $@
	which pylint >> $@

applet/.keystore:
	keytool -genkeypair -keyalg rsa -alias test-only-applet-key \
	-storepass $(_JAVA_KEYSTORE_PWD) -keypass $(_JAVA_KEYSTORE_PWD) \
	-dname "cn=Test User, ou=SR, o=SR, c=UK" -keystore $(_JAVA_KEYSTORE)

folders: $(_FOLDERS)

$(_FOLDERS):
	mkdir -p -m 777 $@
