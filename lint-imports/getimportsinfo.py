
import ast
import json
import os, os.path
import sys
from modulefinder import ModuleFinder
from importvisitor import ImportVisitor

def invertBadmodules(finder):
	missing = {}
	for mod, where in finder.badmodules.iteritems():
		for name in where.iterkeys():
			if name not in missing:
				missing[name] = []
			missing[name].append(mod)
	return missing

def getMissingImports(allMissingImports, module, filename):
	with open(filename, 'r') as f:
		code = f.read()

#	print '---------', filename, '---------'
#	print code

	tree = ast.parse(code, filename)
	modules = allMissingImports.get(module, [])
	# Ignore missing submodules -- these get picked up by pylint
	modules = [m for m in modules if m.find('.') == -1]

#	print 'Missing imports:', modules
	iv = ImportVisitor(modules)
	iv.visit(tree)
	info = iv.getImportsInfo()

	return info

def getLocalPath(mod):
	modPath = mod.__file__
	if modPath is None or not modPath.startswith(basePath):
		return False
	path = modPath[len(basePath)+1:]
#	print name, '   \t', path
	return path

# Bodge the path setup for finding the imports in the file in question
# Pop the apparent path to this file off the front and append the cwd
first = sys.path.pop(0)
sys.path.insert(0, os.getcwd())

finder = ModuleFinder()
mainName = sys.argv[1]
absPath = os.path.abspath(mainName)
basePath = os.path.dirname(absPath)
missingIncludes = {}

try:
	finder.run_script(mainName)
except Exception as ex:
	"Probably an error in the user code"
	if not ex.filename.startswith(basePath):
		raise
	fileName = ex.filename[len(basePath) + 1:]
	missingIncludes['.error'] = { 'file': fileName,
	                              'line': ex.lineno,
	                              'msg': ex.msg
	                            }

includeOriginal = True
allMissingImports = invertBadmodules(finder)

#print 'All missing imports nice:\n', json.dumps(allMissingImports).replace(']', ']\n')

if includeOriginal:
	missingIncludes[mainName] = getMissingImports(allMissingImports, '__main__', mainName)

#print 'All imports:'
for name, mod in finder.modules.iteritems():
	filename = getLocalPath(mod)
	if filename is not False:
		missingIncludes[filename] = getMissingImports(allMissingImports, name, filename)

print json.dumps(missingIncludes).replace('}', '}\n')

#finder.report()
