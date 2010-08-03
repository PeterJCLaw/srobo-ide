_REPOS = repos/1 repos/2

.PHONY: all default dev docs clean applet

# Useful groupings
default: dev
all: dev docs applet

applet: applet/build/checkout.jar

# Actual targets
applet/build/checkout.jar: applet/build.xml
	cd applet/ && ant clean && ant build

clean:
	rm -rf repos html latex
	cd applet/ && ant clean

dev: $(_REPOS)

docs:
	doxygen doxyfile

# Helpers
$(_REPOS):
	mkdir -p $@
	chmod a+rwx $@

