<?php
namespace Mcpuishor\OrderwiseApi;

class XmlResponse 
{
	public $messages;

	public function __construct()
	{
		$this->messages = [];
		return $this;
	}

	public function add(string $message, string $type = "Log")
	{
		array_push($this->messages,[
			"Type" => $type,
			"Message" => $message,
		]);
		return $this;
	}

	public function get()
	{
		return response()->xml(
			[
				'Responses' => [
					'Response' => [
						$this->messages,
					],
				]
			],
		200, [], "XMLFile");
	}
	
	static public function with(string $message, string $type = "Log")
	{
		return response()->xml(
			[
				'Responses' => [
					'Response' => [
						"Type"	=> $type,
						"Message"	=> $message,
					],
				]
			],
		200, [], "XMLFile");
	}
}