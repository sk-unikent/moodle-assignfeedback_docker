#
# Python auto grader.
# Takes one argument - the file to mark, and marks it.
# 
# The script will return a numeric value indicating the grade
# to assign the given file (-1 for failure) and will print
# any notes to stdout.
# 
# Written by Skylar Kelty
#

import sys, assignment

grade = 0

# Check add.
nums = list(zip(range(1, 20, 2), range(2, 40, 4)))
results = [x + y for (x, y) in nums]
actual = [assignment.add(x, y) for (x, y) in nums]
if results == actual:
    grade += 33.33
    print("add() test passed!")
else:
    print("add() test failed!")
    print("Expected: %s" % results)
    print("Actual: %s" % actual)

# Check subtract.
nums = list(zip(range(1, 20, 2), range(2, 40, 4)))
results = [x - y for (x, y) in nums]
actual = [assignment.subtract(x, y) for (x, y) in nums]
if results == actual:
    grade += 33.33
    print("subtract() test passed!")
else:
    print("subtract() test failed!")
    print("Expected: %s" % results)
    print("Actual: %s" % actual)

# Check ziptiply.
nums = range(0, 10)
results = [0, 24, 80, 168, 288]
actual = assignment.ziptiply(nums, nums)
if results == actual:
    grade += 33.33
    print("ziptiply() test passed!")
else:
    print("ziptiply() test failed!")
    print("Expected: %s" % results)
    print("Actual: %s" % actual)

grade = round(grade)

print("Final grade: %i" % grade)

sys.exit(grade)