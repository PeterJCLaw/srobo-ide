_REPOS = repos/1 repos/2
JAVA_FILES = applet/src/org/studentrobotics/ide/checkout/*.java \
	applet/depends/*.jar applet/build.xml

.PHONY: all default dev docs clean applet

# Useful groupings
default: dev
all: dev docs applet

applet: applet/build.xml
	cd applet/ && ant build

# Actual targets
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

