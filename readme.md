## Taint Analysis
SQL injection and other types of injects are among the common techniques to attack web applications, taint analysis uses abstract interpretation to compute taint propagation information and thus identify vulnerabilities in the source code. The popularity of PHP in the domain of web development makes it the target of this project.

The intuitive solution is to embed the analysis inside the PHP interpreter, which involves a fair amount of work, and someone might have already done so. Alternatively, a stand-alone script, which is the solution we settled on, also does the job without modifying the interpreter itself.

The rest of the ducoment explains the structure and usage of the source code, as well as the key ideas behind the program.

## Dependencies
- [php-parser](https://github.com/nikic/PHP-Parser)

To install PHP-Parser:
```bash
$ php composer.phar require nikic/php-parser
```

## Usage
```bash
$ ./main.php < input/1.php
```
To suppress debug information:
```bash
$ ./traverse.php < input/1.php 2> /dev/null
```

## Example
Input
```php
<?php
if(input_from_params()) {
    $a = $_GET[0];
} else {
    $a = mysql_fetch_row();
}
print_($a);

```
Output
```
There is a Persisted XSS vulnerability at line 7
When the taint(source) conditions is satisfied:
input_from_params() == false
There is a Command line injection vulnerability at line 8
When the taint(source) conditions is satisfied:
input_from_params() == true
```

## Structure
- main.php: traverses the AST and perform all the analysis on it
- symbolTable.php: implements SymbolTable class, ArrayTable class and ClassTable class
- taintInfo.php: implements TaintInfo class, SingleTaint class and SingleSanitize class
- condition.php: implements the Condition class and CompoundCondition class, which are the cornerstone of the analyzer

Besides, test files are in input/, demo files are in demo/. All other files are either for test or composer's file.

## Reference
[1] https://www.acsac.org/2005/papers/45.pdf
This is actually a dynamic taint analysis for Java, but it tells me the roadmap of the basic taint analysis.

[2] http://www.icosaedro.it/articoli/php-syntax-yacc.txt
BNF for PHP's grammer

[3] https://github.com/laruence/taint
A php extension of taint analysis, but mainly supports PHP 7 and not have good support for PHP 5. 

[4] https://websec.files.wordpress.com/2010/11/rips-slides.pdf
http://php-security.org/downloads/rips.pdf
RIPS is static vulnerability tool for PHP. It includes taint analysis, which seems very naive according to their paper's description though.

[5] http://www.cs.virginia.edu/nguyen/phprevent/
This paper is advised by David Evans, which is Dr. Nate Paul's advisor. 

[6] https://www.usenix.org/legacy/event/sec05/tech/full_papers/livshits/livshits_html/
This paper is the first paper that I read on this topic. They are using a very sophisticated method to find the vulnerability pattern, similar to how regular expression engine is implemented. 
1. Define a pattern description language
2. Use defined language to describe vulnerability pattern and feed to the engine to construct a DFA
3. Use constructed DFA to match the target code.
The advisor of this paper, Monica Lam, is one of the authors of the famous Dragon Book. She has a series of papers on taint analysis.

## Classes
#### SymbolTable
SymbolTable class is a collection of three symbol tables, namely string symbol table, array symbol table and object symbol table. String symbol table is implemented as a simple associate array. Because array can fetch index and object can fetch property so they should be implemented as a two dimensional array. In this implementation, the second level array is implemented as an object which contains an array and other relevant methods. There is no integer array or boolean array because they cannot be tainted and thus are never in any symbol table. All the tables use the same algorithm to calculate the taint condition.

#### ArrayTable
The tricky thing that array table has to implement is situation as blow
```php
$a[0] = "tainted";
$a[foo()] = "tainted";
execute($a[0]);
execute($a[1]);
```
The analyzer should be able to point out `foo()==1` is the taint condition for $a[1] and $a[0] must be tainted. The way this is implemented is that there is an invisible index in the array. If the array uses an expression to index, suppose it is working on this invisible index. For instance, after `$a[foo()] = "tainted";`, the invisible index will be appended a new condition `old condition AND (foo() == "pending")`, suppose the invisible index is called "pending". Also, when an uncertain index was untainted, still operate on the invisible index.

#### ClassTable
This class should've be called ObjectTable, but somehow when I wrote the initial code I used word class whenever I wanted to say object. I think it is because I was working with hotspot klass at that point, so I was too used to the word class. ClassTable is basically the same as a simple array and also implements the condition calculation algorithm.

#### TaintInfo
TaintInfo is an extension to support of more than one type of taint. And it also keeps track of sanitize information, which is used when a vulnerability check happens. A lot of vital operations are also implemented in this class, such as merging two taints.

#### SingleTaint
A TaintInfo object contains a list of SingleTaint object and a list of SingleSanitize object. A SingleTaint object represent one taint type, its taint condition and other manipulation of taint condition. A condition can either be a Condition object or a CompoundCondition object.

#### SingleSanitize
Same as SingleTaint.

#### Condition
A implementation of one single contion such as `$a==true`. Include basic operations on a condition such as "setAlwaysTrue", "setNot" and printing.

#### CompoundCondition
Represent a condition logic expression such as `$b==true AND $a!=true`. A CompoundCondition object has a left condition, right condition and operator. Both left condition and right condition can be either a Condition obejct or another CompoundCondition object. In this way, conditions are concatenated.

## Issues
One problem I was struggling with was that I wanted to use PHP's obejct assignment feature to implemented the object assignment problem. The basic idea is that when copy an array table to the inner symbol table, do a deep copy, and do a shallow copy for class table. And shallow copy is default for object assignment.
```php
<?php
function modify0($a) {
    $a = "safe";
}

function modify1($a) {
    $a[0] = "safe";
}

function modify2($a) {
    // if this is uncommented, $c should not be tainted after going through the function call 
    // $a = new A(); 
    $a->foo = "foo";
}

$a = $_GET['u'];
$b[0] = $_GET['u'];
$c->foo = $_GET['u'];
modify0($a);
modify1($b);
modify2($c); // should be tainted
system($a);
system($b[0]);
```
My thoery should be right, but what happened was even if `// $a = new A();` is uncommented, `$c ->foo` will still be changed.

Besides, trivial cases of taint propagation, such as string concatenation, are left unimplemented.
