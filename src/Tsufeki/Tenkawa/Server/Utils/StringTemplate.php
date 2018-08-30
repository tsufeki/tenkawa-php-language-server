<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

class StringTemplate implements Template
{
    /**
     * @var string
     */
    private $template;

    public function __construct(string $template)
    {
        $this->template = $template;
    }

    /**
     * @param array<string,string> $variables
     *
     * @resolve string
     */
    public function render(array $variables): \Generator
    {
        $curlyVariables = [];
        foreach ($variables as $name => $value) {
            $curlyVariables['{{' . $name . '}}'] = $value;
        }

        return strtr($this->template, $curlyVariables);
        yield;
    }
}
