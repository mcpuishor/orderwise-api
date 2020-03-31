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
	private $file;
	

	public function __construct($stream, $file=null)
	{
		$this->setFilename($file);
		$this->stream = $stream;
		$this->previous_encoding = mb_internal_encoding();
		mb_internal_encoding('UTF-8');
		$this->storage = Storage::disk('local');
		$this->storage->put($this->file, $this->xml($this->stream));
    	$this->entities= new IlluminateCollection();
		return $this;
	}

	public function setFilename($file)
	{
		$this->file = $file ?? self::TEMP_FILE;	
	}

	public function get($file= null, $delete = false)
	{
		$this->setFilename($file);
		if (!Validator::file($this->file)) {
			throw new \Exception("Error in processing the import. Post is not a valid XML stream.");
		}
    	$file = $this->storage
    				->getAdapter()
    				->applyPathPrefix($this->file);
    	StreamParser::xml($file)
    		->each(function($entity){
				$this->entities->push($entity);
			});
		if ($delete) {
			$this->storage->delete($$this->file);
		}
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
	}
}