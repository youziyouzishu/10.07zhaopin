<?php

namespace app\command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RegexIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TableModelMapping extends Command
{
    protected static $defaultName = 'TableModelMapping';
    protected static $defaultDescription = 'generate table model mapping';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name description');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = base_path('plugin');
        $files = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)), "/\.php$/");
        $map = [];
        foreach ($files as $file) {
            if (!preg_match("/model(s)?/i", $file->getPath())) {
                continue;
            }

            $class = $this->getClassNamespaceFromFile($file);
            if (!class_exists($class)) {
                continue;
            }
            $reflect = new ReflectionClass($class);

            $object = $reflect->newInstanceArgs();
            if (class_exists(\think\Model::class) && $object instanceof \think\Model) {
                $map[$object->getConfig("database")][$object->getTable()] = $class;
                continue;
            }
            if (class_exists(\Illuminate\Database\Eloquent\Model::class) && $object instanceof \Illuminate\Database\Eloquent\Model) {
                $map[$object->getConnection()->getDatabaseName()][$object->getConnection()->getTablePrefix() . $object->getTable()] = $class;
            }
        }

        $data = <<<HEREA
<?php

return %s;
HEREA;
        file_put_contents(base_path() . "/vendor/chance-fyi/operation-log/cache/table-model-mapping.php", sprintf($data, var_export($map, true)));

        $output->writeln('success');
        return self::SUCCESS;
    }

    protected function getClassNamespaceFromFile($file): string
    {
        $content = file_get_contents($file->getRealPath());
        $tokens = token_get_all($content);
        $namespace = "";
        $class = "";
        $count = count($tokens);
        $i = 0;
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }
            }
            if (
                is_array($token)
                && $i >= 2
                && $tokens[$i - 2][0] == T_CLASS
                && $tokens[$i - 1][0] == T_WHITESPACE
                && $token[0] == T_STRING
            ) {
                $class = trim($tokens[$i][1]);
                break;
            }

            $i++;
        }

        return $namespace . "\\" . $class;
    }
}
