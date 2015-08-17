# moodle-mod_dockerassignment
Assignment submissions that are auto-graded in docker containers (experimental)

## Testing docker
```
▶ cd tests/fixtures
▶ docker build -t autograde1 .
▶ docker run -t autograde1    
add() test passed!
subtract() test passed!
Final grade: 100
▶ echo $?
100
```
