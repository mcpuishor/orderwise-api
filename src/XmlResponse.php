<?php
namespace Mcpuishor\OrderwiseApi;

class XmlResponse 
{
	
	static public function with(string $message, string $type = "Log")
	{
		return response()->xml(
			[
				'Response' => [
					"Type"	=> $type,
					"Message"	=> $message,
				],
			],
		200, [], "Responses");
	}
}