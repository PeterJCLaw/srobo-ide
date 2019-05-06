from __future__ import print_function

import ast

class ImportVisitor(ast.NodeVisitor):

	def __init__(self, names):
		self._names = names
		self._info = {}

	def visit_Import(self, node):
		self._visitImport(node, [a.name for a in node.names])

	def visit_ImportFrom(self, node):
		parts = [node.module+'.'+a.name for a in node.names]
		parts.append(node.module)
		self._visitImport(node, parts)

	def _visitImport(self, node, imports):
		for name in imports:
			if name in self._names:
				self._info[name] = node.lineno

		return
		# allow parser to continue to parse the statement's children
		super(ImportVisitor, self).generic_visit(node)

	def getImportsInfo(self):
		return self._info

def _test():
	code = """
import stuff
from bananas import taste
from pounds import pence
def foo():
	import bacon as face, nose
a = b + 5
"""
	print(code)
	print('----')

	tree = ast.parse(code)
#	print(dir(tree))
#	print(tree.__class__)
	iv = ImportVisitor(['bacon', 'bananas', 'pounds.pence'])
	iv.visit(tree)

	print(iv.getImportsInfo())

if __name__ == '__main__':
	_test()
