<?php

require __DIR__ . '/../vendor/autoload.php';

$classDir = realpath(__DIR__ . '/../classes');
$rootDir = realpath(__DIR__ . '/..');

$loader = new Nette\Loaders\RobotLoader;
$loader->addDirectory($classDir);
$loader->setCacheStorage(new Nette\Caching\Storages\FileStorage(__DIR__ . '/../temp'));
$loader->register();

foreach ($loader->getIndexedClasses() as $className => $classPath) {
    if (Nette\Utils\Strings::endsWith($className, 'Core')) {
        $classBody = file_get_contents($classPath);
        $interface = Nette\Utils\Strings::match($classBody, "~interface {$className}~");
        if ($interface) {
            continue;
        }

        $descendantName = Nette\Utils\Strings::replace($className, '~Core$~', '');
        $descendantPath = str_replace($classDir, "{$rootDir}/override/classes", realpath($classPath));
        $descendant = new Nette\PhpGenerator\ClassType($descendantName);
        $descendant->setAbstract(Nette\Utils\Strings::match($classBody, "~abstract class {$className}~") ? TRUE : FALSE);
        $descendant->setExtends($className);
        file_put_contents($descendantPath, "<?php\n\n$descendant");
    }
}
