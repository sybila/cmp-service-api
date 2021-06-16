
"""
Created on Mon May  3 09:21:41 2021

@author: Radoslav Doktor
"""
# Python program to print connected
# components in an undirected graph

import sys, getopt, numpy
import ast
from numpy.linalg import matrix_rank

class Graph:

	# init function to declare class variables
	def __init__(self, V):
		self.V = V
		self.adj = [[] for i in range(V)]

	def DFSUtil(self, temp, v, visited):

		# Mark the current vertex as visited
		visited[v] = True

		# Store the vertex to list
		temp.append(v)

		# Repeat for all vertices adjacent
		# to this vertex v
		for i in self.adj[v]:
			if visited[i] == False:

				# Update the list
				temp = self.DFSUtil(temp, i, visited)
		return temp

	# method to add an undirected edge
	def addEdge(self, v, w):
		self.adj[v].append(w)
		self.adj[w].append(v)

	# Method to retrieve connected components
	# in an undirected graph
	def connectedComponents(self):
		visited = []
		cc = []
		for i in range(self.V):
			visited.append(False)
		for v in range(self.V):
			if visited[v] == False:
				temp = []
				cc.append(self.DFSUtil(temp, v, visited))
		return cc

def getStochDistinctComplexes(matrix):
    mx = numpy.transpose(matrix)
    reactants = []
    products = []
    for i, row in enumerate(mx):
        product = '';
        reactant = '';
        for j, element in enumerate(row):
            if element > 0:
                product = product + str(j) + str(element)
            elif element < 0:
                reactant = reactant + str(j) + str(abs(element))
        reactants.append(reactant)
        products.append(product)

    if len((list(list(set(reactants)-set(products)) 
                + list(set(products)-set(reactants))))) != 0:
        print('This system is not weakly reversible, and therefore Deficiency Zero Theorem cannot be applied.');
        sys.exit(2)
    subs = 0;
    for p, element in enumerate(reactants):
        reactants[p] = subs;
        products = [ subs if x == element else x for x in products ]
        subs = subs + 1
    return reactants, products
        

def main(argv):
    matrix = ''
    try:
       opts, args = getopt.getopt(argv,"m:",["matrix="])
    except getopt.GetoptError:
       print ('-i MATRIX')
       sys.exit(2)
    for opt, arg in opts:
       if opt in ("-m", "--matrix"):
          matrix = arg
    matrix = ast.literal_eval(matrix)
    reactants, products = getStochDistinctComplexes(matrix)
    n = len(reactants)
    s = matrix_rank(numpy.transpose(matrix))
    g = Graph(len(products))
    for j, element in enumerate(reactants):
        g.addEdge(element, products[j])
    l = len(g.connectedComponents())
    print("This is a weakly reversible deficiency zero network, with:")
    print("n = " + str(n) + " stoichiometric distinct complexes,|")
    print("l = " + str(l) + " linkage classes,|")
    print("s = " + str(s) + " dimensions,|")
    print("Deficiency = n - l - s = " + str(n - l - s) + ",|")
    if (n - l - s) == 0:
        print("this mass action system is complex balanced.|" +
              "It exhibits locally stable dynamics, for all rate constant choices." )
    else:
        print("This system is weakly reversible, but no other conclusion can be made. ")

# Driver Code
if __name__ == "__main__":

	# Create a graph given in the above diagram
	# 5 vertices numbered from 0 to 4
    main(sys.argv[1:])
