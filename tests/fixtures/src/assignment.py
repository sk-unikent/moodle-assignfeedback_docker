#
# My first assignment!
# University is so exciting!
# 
# Student Name: Skylar Kelty
# Student ID: 50 31337
#

#
# This function should return the sum of both arguments.
#
def add(first, second):
	return first + second

#
# This function should return the difference between both arguments.
#
def subtract(first, second):
	return first - second

#
# Given two lists of numbers, add them together, then multiply
# every other number by the next.
#
def ziptiply(firstset, secondset):
    set = [x + y for (x, y) in zip(firstset, secondset)]
    return [x * y for (x, y) in zip(set[0::2], set[1::2])]
