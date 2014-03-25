<?php

namespace AramisAuto\Component\Preprocessor;

use Symfony\Component\Finder\Finder;

class Preprocessor
{
    public $mapTokens = array();
    public $enclosing;
    public $prefix;
    public $suffix;

    public function __construct(array $data, $prefix = '', $enclosing = '@', $separator = '.', $suffix = '-dist')
    {
        $this->mapTokens = $data;
        $this->enclosing = $enclosing;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
    }

    public function preprocess($directory)
    {
        // Find files that will be preprocessed
        $finder = new Finder();
        $files = $finder->files()->name('*'.$this->suffix)->in($directory);

        // Store rendered files
        $rendered = array();

        // Preprocess files
        foreach ($files as $file) {
            // Original file path
            $pathOrig = $file->getPath().'/'.$file->getFilename();

            // Path to rendered file
            $pathRendered = sprintf(
                '%s/%s',
                $file->getPath(),
                basename($this->replaceTokens($file->getFilename()), $this->suffix)
            );
            file_put_contents($pathRendered, $this->replaceTokens(file_get_contents($pathOrig)));

            // Add to store
            $rendered[] = array($pathOrig, $pathRendered);
        }

        return $rendered;
    }

    public function replaceTokens($contents)
    {
        foreach ($this->mapTokens as $token => $replacement) {
            $contents = str_replace(
                sprintf('%s%s%s%s', $this->enclosing, $this->prefix, $token, $this->enclosing),
                $replacement,
                $contents
            );
        }

        return $contents;
    }
}
