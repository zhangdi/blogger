<?php
/**
 * @link http://ipaya.cn/
 * @copyright Copyright (c) 2016 ipaya.cn
 * @license http://ipaya.cn/license/
 */

namespace app\components;


use cebe\markdown\GithubMarkdown;

class Post
{
    public $title;
    public $contents;
    public $url;
    public $publishDatetime;

    public static function parse($contents): Post
    {
        list($header, $content) = explode('[======]', $contents);

        $headerLines = preg_split('/\n/', $header, -1, PREG_SPLIT_NO_EMPTY);
        $meta = [];
        foreach ($headerLines as $line) {
            if (!empty(trim($line)) && stripos($line, ':') !== false) {
                list($key, $value) = @explode(':', $line);
                $meta[trim($key)] = trim($value);
            }

        }
        $parser = new Post();
        $markdownParser = new GithubMarkdown();
        $parser->contents = $markdownParser->parse($content);
        $parser->title = $meta['title'];
        $parser->publishDatetime = $meta['publishDatetime']??date("Y-m-d H:i");
        return $parser;
    }
}