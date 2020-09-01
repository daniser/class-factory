<?php

declare(strict_types=1);

namespace TTBooking\ClassFactory;

use Closure, Countable, Generator, IteratorAggregate, ReflectionClass, ReflectionException;

/**
 * @property-read Generator $extends
 * @property-read Generator $implements
 * @property-read Generator $uses
 */
final class GenericClass implements IteratorAggregate, Countable
{
    /** @var string */
    private static string $tempDir = '';

    /** @var bool */
    private static bool $useEval = false;

    /** @var array<string, self> */
    private static array $templates = [];

    /** @var string[] */
    private array $dependencies = [];

    /**
     * @param string $path
     *
     * @return string
     */
    public static function getTempPath(string $path = ''): string
    {
        return (self::$tempDir ?: sys_get_temp_dir()).($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * @param string $tempDir
     *
     * @return void
     */
    public static function setTempDirectory(string $tempDir = ''): void
    {
        self::$tempDir = $tempDir;
    }

    /**
     * @return bool
     */
    public static function isUsingEval(): bool
    {
        return self::$useEval;
    }

    /**
     * @param bool $useEval
     *
     * @return void
     */
    public static function useEval(bool $useEval = true): void
    {
        self::$useEval = $useEval;
    }

    /**
     * @param string $name
     * @param string[] $dependencies
     *
     * @return self
     */
    public static function setTemplate(string $name, array $dependencies = []): self
    {
        return self::$templates[$name] = new self(...$dependencies);
    }

    /**
     * Generic class constructor.
     *
     * @param string ...$dependencies
     *
     * @throws ClassFactoryException
     */
    public function __construct(string ...$dependencies)
    {
        $this->addDependency(...$dependencies);
    }

    /**
     * @param string ...$dependencies
     *
     * @return $this
     */
    public function addDependency(string ...$dependencies): self
    {
        $this->dependencies = array_unique(array_merge($this->dependencies, $dependencies));

        return $this;
    }

    /**
     * @return Generator
     */
    public function getIterator(): Generator
    {
        foreach ($this->dependencies as $dependency) {
            array_key_exists($dependency, self::$templates)
                ? yield from self::$templates[$dependency]
                : yield $dependency;
        }
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return iterator_count($this);
    }

    /**
     * @param string $name
     *
     * @return Generator
     */
    public function __get(string $name): Generator
    {
        foreach ($this as $dependency) {

            try {
                $depReflection = new ReflectionClass($dependency);
            } catch (ReflectionException $e) {
                throw new ClassFactoryException('Given dependency is missing.', 0, $e);
            }

            if ($depReflection->isInterface()) {
                $name === 'implements' && yield $dependency;
            } elseif ($depReflection->isTrait()) {
                $name === 'uses' && yield $dependency;
            } else {
                $name === 'extends' && yield $dependency;
            }

        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return in_array($name, ['extends', 'implements', 'uses'], true);
    }

    /**
     * @param mixed ...$constructorArgs
     *
     * @return object
     */
    public function __invoke(...$constructorArgs): object
    {
        $factory = self::isUsingEval() ? eval($this) : $this->loadFactory();

        return call_user_func_array($factory, $constructorArgs);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->formatFactoryStub();
    }

    /**
     * @return Closure
     */
    private function loadFactory(): Closure
    {
        /** @noinspection PhpIncludeInspection */
        return require $this->ensureFactoryExists();
    }

    /**
     * @return string
     */
    private function ensureFactoryExists(): string
    {
        $code = '<?php '.$this;

        if (file_exists($path = self::getTempPath('factory-'.sha1($code).'.php'))) {
            return $path;
        }

        file_put_contents($path, $code);

        return $path;
    }

    /**
     * @throws ClassFactoryException
     *
     * @return string
     */
    private function formatFactoryStub(): string
    {
        $replacements = [
            '{{ extends }}' => ' extends %s',
            '{{ implements }}' => ' implements %s',
            '{{ uses }}' => ' use %s; ',
        ];

        foreach ($replacements as $token => &$replacement) {
            $token = trim($token, '{ }');
            $dependencyList = implode(', ', iterator_to_array($this->$token, false));
            $replacement = iterator_count($this->$token) ? sprintf($replacement, $dependencyList) : '';
        }

        return strtr($this->getFactoryStub(), $replacements);
    }

    /**
     * @return string
     */
    private function getFactoryStub(): string
    {
        return 'return fn (...$args) => new class(...$args){{ extends }}{{ implements }} {{{ uses }}};';
    }
}
