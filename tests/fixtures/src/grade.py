#
# Python auto grader.
# Takes one argument - the file to mark, and marks it.
# 
# The script will return a numeric value indicating the grade
# to assign the given file (-1 for failure) and will print
# any notes to stdout.
#

import sys, assignment

grade = 0
nums = list(zip(range(1, 20, 2), range(2, 40, 4)))

# Check add.
results = [x + y for (x, y) in nums]
actual = [assignment.add(x, y) for (x, y) in nums]
if results == actual:
    grade += 50
    print("add() test passed!")
else:
    print("add() test failed!")
    print("Expected: %s" % results)
    print("Actual: %s" % actual)

# Check subtract.
results = [x - y for (x, y) in nums]
actual = [assignment.subtract(x, y) for (x, y) in nums]
if results == actual:
    grade += 50
    print("subtract() test passed!")
else:
    print("subtract() test failed!")
    print("Expected: %s" % results)
    print("Actual: %s" % actual)

print("Final grade: %i" % grade)

sys.exit(grade)