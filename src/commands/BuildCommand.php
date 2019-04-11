<?php
/**
 * @link http://ipaya.cn/
 * @copyright Copyright (c) 2016 ipaya.cn
 * @license http://ipaya.cn/license/
 */

namespace app\commands;


use app\components\Post;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class BuildCommand extends Command
{
    protected $targetDir;
    /**
     * @var Environment
     */
    protected $twig;

    protected function configure()
    {
        $this->setName('build')
            ->setDescription('构建博客站点')
            ->addOption('target-dir', null, InputOption::VALUE_REQUIRED, '输出目录')
            ->addOption('post-dir', null, InputOption::VALUE_REQUIRED, '文章目录')
            ->addOption('theme-dir', null, InputOption::VALUE_REQUIRED, '主题目录', 'default')
            ->addOption('page-size', null, InputOption::VALUE_REQUIRED, '每页文章数量', 10)
            ->addOption('site-title', null, InputOption::VALUE_REQUIRED, '站点标题', "我的站点");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targetDir = $input->getOption('target-dir');
        if ($targetDir == null) {
            $output->writeln('<error>请填写输出目录</error>');
            return 1;
        }

        $this->targetDir = $targetDir;

        $themeDir = $input->getOption('theme-dir');
        if (!file_exists($themeDir)) {
            $output->writeln("<error>主题目录 \"{$themeDir}\"不存在</error>");
            return 1;
        }
        $pageSize = $input->getOption('page-size');
        $siteTitle = $input->getOption('site-title');
        $postDir = $input->getOption('post-dir');

        // 读取原始内容
        $finder = new Finder();
        $finder->in($postDir)
            ->name('*.md')
            ->files();

        // 复制静态资源
        $filesystem = new Filesystem();
        $filesystem->mirror($themeDir . '/static', $this->targetDir . '/static');

        $loader = new FilesystemLoader($themeDir);
        $this->twig = new Environment($loader);

        $posts = [];
        $urls = [];
        $index = 1;
        foreach ($finder->getIterator() as $file) {
            $contents = $file->getContents();
            $post = Post::parse($contents);
            $post->url = '/html/' . $file->getRelativePath() . '/' . $file->getBasename('.md') . '.html';
            if (in_array($post->url, $urls)) {
                $output->writeln("<error>url 已存在: ${file}</error>");
                return 1;
            }

            $output->write("生成 $file ...");
            $this->generateHtmlFile('post.html', $post->url, ['post' => $post, 'siteTitle' => $siteTitle]);

            $output->writeln("[ok]");

            $urls[] = $post->url;

            // 获取Id,第一个短横线前的数字
            $idPos = stripos('-', $file->getBasename());
            if ($idPos !== false) {
                $id = substr($file->getBasename(), 0, $idPos + 1);
            } else {
                $id = "$index";
            }
            $posts[$id] = $post;
            $index++;
        }

        krsort($posts);

        $pieces = array_chunk($posts, $pageSize);
        $size = count($pieces);
        foreach ($pieces as $key => $value) {
            if ($key == 0) {
                $title = '首页';
                $htmlFile = 'index.html';
            } else {
                $p = $key + 1;
                $title = "第 {$p} 页";
                $htmlFile = "index-{$p}.html";
            }

            $prevPage = null;
            $nextPage = null;
            $currentPage = $key + 1;
            if ($currentPage > 1) {
                if ($currentPage == 2) {
                    $prevPage = '/index.html';
                } else {
                    $prevPage = '/index-' . ($currentPage - 1) . '.html';
                }
            }

            if ($currentPage < $size) {
                $nextPage = '/index-' . ($currentPage + 1) . '.html';
            }

            $output->write("生成 {$htmlFile} ...");


            $this->generateHtmlFile('index.html', $htmlFile, [
                'posts' => $value,
                'prevPage' => $prevPage,
                'nextPage' => $nextPage,
                'siteTitle' => $siteTitle,
                'title' => $title,
            ]);
            $output->writeln("[ok]");
        }


    }

    /**
     * @param $template
     * @param $outputFile
     * @param array $context
     */
    protected function generateHtmlFile($template, $outputFile, array $context = [])
    {
        $indexHtml = $this->twig->render($template, $context);
        $absoluteFile = $this->targetDir . '/' . $outputFile;
        $dir = dirname($absoluteFile);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($absoluteFile, $indexHtml);
    }
}