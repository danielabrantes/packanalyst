<?php
namespace Mouf\Packanalyst;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use Mouf\Packanalyst\Entities\ItemEntity;
use Composer\Package\Package;
use Mouf\Packanalyst\Services\StoreInDbNodeVisitor;
use HireVoice\Neo4j\Repository;
use Mouf\Packanalyst\Entities\PackageEntity;
use Mouf\Packanalyst\Repositories\PackageVersionRepository;
use Mouf\Packanalyst\Repositories\ItemNameRepository;
use Mouf\Packanalyst\Entities\PackageVersionEntity;
use PhpParser\Error;
use Psr\Log\LoggerInterface;
use Mouf\Packanalyst\Dao\ItemDao;

/**
 * This package is in charge of detecting classes/interfaces/traits inside a package.
 * 
 * @author David Négrier <david@mouf-php.com>
 */
class ClassesDetector extends NodeVisitorAbstract
{
	private $parser;
	private $itemNameRepository;
	private $itemRepository;
	private $packageVersionRepository;
	private $logger;
	private $itemDao;
	
	public function __construct(ItemNameRepository $itemNameRepository, Repository $itemRepository, PackageVersionRepository $packageVersionRepository, LoggerInterface $logger, ItemDao $itemDao) {
		// use the emulative lexer here, as we are running PHP 5.2 but want to parse PHP 5.3
		//$this->parser        = new PhpParser\Parser(new PhpParser\Lexer\Emulative);
		$this->parser        = new Parser(new Lexer());
		$this->itemNameRepository = $itemNameRepository;
		$this->itemRepository = $itemRepository;
		$this->packageVersionRepository = $packageVersionRepository;
		$this->logger = $logger;
		$this->itemDao = $itemDao;
	}
	
	/**
	 * Returns the classes / interfaces / traits / functions of the package.
	 * 
	 * @return array<string, string>
	 */
	public function storePackage($basePath, array $packageVersion) {
		
		$this->traverser     = new NodeTraverser();
		
		$this->traverser->addVisitor(new NameResolver()); // we will need resolved names
		$this->traverser->addVisitor(new StoreInDbNodeVisitor($packageVersion, $this->itemDao));     // our own node visitor
		
		
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));
		$files = new \RegexIterator($files, '/\.php$/');
		
		$this->classes = [];
		$this->interfaces = [];
		$this->traits = [];
		$this->functions = [];
		
		foreach ($files as $file) {
			try {
				// read the file that should be converted
				$code = file_get_contents($file);
		
				// parse
				
				$stmts = $this->parser->parse($code);
		
				// traverse
				$stmts = $this->traverser->traverse($stmts);
		
				
			} catch (Error $e) {
				$this->logger->warning("PHP error detected in file {file}. Ignoring file. Error: {errorMsg}", [
					"file" => $file,
					"errorMsg" => $e->getMessage(),
					"exception" => $e
				]
				);
			}
		}
		
		//return $classMap;
	}
	
}
