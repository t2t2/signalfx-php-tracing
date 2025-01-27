--TEST--
[Sandbox regression] Tracing closure set from inside non-static method
--SKIPIF--
<?php if (PHP_VERSION_ID < 80000) die('skip: Dispatch can be overwritten on PHP < 8'); ?>
--ENV--
SIGNALFX_TRACE_DEBUG=1
--FILE--
<?php
class Test {
    public function m($v){
        echo "METHOD " . $v . PHP_EOL;
    }
}

$variable = 1000;

final class TestSetup {
    public function setup(){
        DDTrace\trace_method("Test", "m", function($span, array $args) {
            $variable = $args[0] + 10;
            echo "HOOK " . $variable . PHP_EOL;
        });
    }
    public function setup_ext($j){
        DDTrace\trace_method("Test", "m", function($span, array $args) use ($j){
            global $variable;
            $variable += $args[0] + $j;
            echo "HOOK " . $variable . PHP_EOL;
        });
    }
}

// use convoluted way to execute to test if it also works
$o = new TestSetup();
$reflectionMethod = new ReflectionMethod('TestSetup', 'setup');
$reflectionMethod->invoke($o);

(new Test())->m(1);

$o->setup_ext(100);

(new Test())->m(1);
(new Test())->m(10);

?>
--EXPECT--
METHOD 1
HOOK 11
Cannot overwrite existing dispatch for 'm()'
METHOD 1
HOOK 11
METHOD 10
HOOK 20
