<?php 
namespace Mcpuishor\OrderwiseApi;

use Illuminate\Support\Facades\Storage;
use Mcpuishor\OrderwiseApi\XmlResponse;

use Mcpuishor\XmlUtil\Validator,
	Illuminate\Support\Str,
	Rodenastyle\StreamParser\StreamParser,
	Illuminate\Support\Collection as IlluminateCollection;

class Extract {
	const TEMP_FILE = "temp/stock.xml";
	private $previous_encoding;
	private $storage;
	private $stream;
	

	public function __construct($stream)
	{
		$this->stream = $stream;
		$this->previous_encoding = mb_internal_encoding();
		mb_internal_encoding('UTF-8');
		$this->storage = Storage::disk('local');
		$this->storage->put(self::TEMP_FILE, $this->xml($this->stream));
    	$this->entities= new IlluminateCollection();
		return $this;
	}

	public function get()
	{
		if (!Validator::file(self::TEMP_FILE)) {
			throw new \Exception("Error in processing the import. Post is not a valid XML stream.");
		}
    	$file = $this->storage
    				->getAdapter()
    				->applyPathPrefix(self::TEMP_FILE);
    	StreamParser::xml($file)
    		->each(function($entity){
				$this->entities->push($entity);
			});
		$this->storage->delete(self::TEMP_FILE);
    	$this->trimNestedObjects();
    	return $this->entities;
	}

	private function xml($content)
    {
		$content = urldecode($content);
		$content = preg_replace('/ExportData\=/', '', $content);
		return $content;
    }

	protected function trimNestedObjects()
	{
		$this->entities = $this->entities
						->map(
							function($entity){
								return $entity->map(function($attribute){
									if (is_object($attribute) && $attribute->isEmpty()) {
										return "";
									}
									return (string) $attribute;
								});
							}
						);
	}

	public function __destruct()
	{
    	mb_internal_encoding($this->previous_encoding);
		parent::__destruct();
	}
}