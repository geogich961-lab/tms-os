<?php
declare(strict_types=1);
final class LogService
{
    private string $dir;
    public function __construct(){ $h=getenv('HOME')?:'/data/data/com.termux/files/home'; $this->dir=$h.'/logs'; }
    public function files(): array { $o=[]; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir,FilesystemIterator::SKIP_DOTS)); foreach($it as $f){if($f->isFile()&&!$f->isLink())$o[]=str_replace($this->dir.'/','',$f->getPathname());}sort($o);return $o;}
    public function read(string $rel): string { $base=realpath($this->dir);$p=realpath($this->dir.'/'.ltrim($rel,'/'));if($base===false||$p===false||!str_starts_with($p,$base.'/')||!is_file($p))throw new RuntimeException('Log không hợp lệ.');$size=filesize($p)?:0;$fh=fopen($p,'rb');if(!$fh)return '';if($size>200000)fseek($fh,-200000,SEEK_END);$c=stream_get_contents($fh)?:'';fclose($fh);return $c;}
}
