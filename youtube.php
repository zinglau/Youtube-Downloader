<?php

class youtube
{
	public $id = '';
	public $title = '';
	public $duration = 0;
	
	public $images = array();
	public $videos = array();
	
	private $sizes = array();
	private $cipher = array();
	private $context = array('ssl' => array('verify_peer' => false));
	
	public function __construct($v = 'dQw4w9WgXcQ')
	{
		$contents = file_get_contents('https://www.youtube.com/watch?v='.$v, false, stream_context_create($this->context));
		
		preg_match('#ytplayer\.config = (\{.+\});#U', $contents, $match);
		
		$config = json_decode($match[1], true);
		
		$this->id = $config['args']['vid'];
		$this->title = $config['args']['title'];
		$this->duration = $config['args']['length_seconds'];
		
		$this->sizes($config['args']['fmt_list']);
		
		$this->cipher($config['assets']['js']);
		
		$this->videos($config['args']['adaptive_fmts']);
		$this->videos($config['args']['url_encoded_fmt_stream_map']);
		
		if(isset($config['args']['thumbnail_url'])) $this->images['min'] = $config['args']['thumbnail_url'];
		if(isset($config['args']['iurlmaxres'])) $this->images['max'] = $config['args']['iurlmaxres'];
		if(isset($config['args']['iurlmq'])) $this->images['low'] = $config['args']['iurlmq'];
		if(isset($config['args']['iurl'])) $this->images['mid'] = $config['args']['iurl'];
		if(isset($config['args']['iurlsd'])) $this->images['high'] = $config['args']['iurlsd'];
	}
	
	private function sizes($fmts)
	{
		preg_match_all('#([0-9]+)/([0-9]+x[0-9]+)#', $fmts, $match);
		
		$this->sizes = array_combine($match[1], $match[2]);
	}
	
	private function extention($type)
	{
		if(preg_match('#/3gpp#', $type)) return '3GP';
		if(preg_match('#/webm#', $type)) return 'WEBM';
		if(preg_match('#/x\-flv#', $type)) return 'FLV';
		
		return 'MP4';
	}
	
	private function cipher($js)
	{
		$contents = file_get_contents('http:'.$js, false, stream_context_create($this->context));
		
		preg_match('#"signature",([A-Za-z]+)#', $contents, $match);
		$function = $match[1];
		
		preg_match('#([A-Za-z0-9]+):function\(a\)\{a\.reverse\(\)\}#', $contents, $match);
		$action[$match[1]] = 'reverse';
		
		preg_match('#([A-Za-z0-9]+):function\(a,b\)\{a\.splice\(0,b\)\}#', $contents, $match);
		$action[$match[1]] = 'slice';
		
		preg_match('#([A-Za-z0-9]+):function\(a,b\)\{var c=a\[0\];a\[0\]=a\[b%a\.length\];a\[b\]=c\}#', $contents, $match);
		$action[$match[1]] = 'swap';
		
		preg_match('#'.$function.'=function\(a\)\{a=a\.split\(""\);([^\}]+)return a\.join\(""\)}#', $contents, $match);
		$contents = $match[1];
		
		preg_match_all('#[A-Za-z0-9]+\.([A-Za-z0-9]+)\(a,([0-9]+)\)#', $contents, $match);
		
		foreach($match[0] as $key => $temp)
		{
			$this->cipher[$key] = array
			(
				'action' => $action[$match[1][$key]],
				'value' => $match[2][$key]
			);
		}
	}
	
	private function signature($s)
	{
		foreach($this->cipher as $cipher)
		{
			if($cipher['action'] == 'swap')
			{
				$t = $s[0];
				
				$s[0] = $s[$cipher['value']%strlen($s)];
				
				$s[$cipher['value']] = $t;
			}
			else if($cipher['action'] == 'slice')
			{
				$s = substr($s, $cipher['value']);
			}
			else if($cipher['action'] == 'reverse')
			{
				$s = strrev($s);
			}
		}
		
		return $s;
	}
	
	private function videos($fmts)
	{
		$streams = explode(',', $fmts);
		
		foreach($streams as $stream)
		{
			parse_str($stream, $video);
			
			if(array_key_exists($video['itag'], $this->sizes))
			{
				$video['size'] = $this->sizes[$video['itag']];
			}
			
			$video['extention'] = $this->extention($video['type']);
			
			if(!isset($video['size']))
			{
				$video['size'] = '-';
				$video['type'] = 'audio';
			}
			else if(isset($video['quality']))
			{
				$video['type'] = 'video & audio';
			}
			else
			{
				$video['type'] = 'video';
			}
			
			if(isset($video['s']))
			{
				$video['url'] .= '&signature='.$this->signature($video['s']);
			}
			
			if(!isset($video['fps'])) $video['fps'] = '-';
			if(!isset($video['clen'])) $video['clen'] = '-';
			if(!isset($video['bitrate'])) $video['bitrate'] = '-';
			
			$this->videos[] = array
			(
				'url' => $video['url'],
				'fps' => $video['fps'],
				'itag' => $video['itag'],
				'size' => $video['size'],
				'type' => $video['type'],
				'lenght' => $video['clen'],
				'bitrate' => $video['bitrate'],
				'extention' => $video['extention']
			);
		}
	}
}

?>
