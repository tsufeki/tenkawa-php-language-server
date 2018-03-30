<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\DocComment;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Server\Document\Document;

class InheritPhpDocClassResolverExtension implements ClassResolverExtension
{
    public function resolve(ResolvedClassLike $class, Document $document): \Generator
    {
        $this->inheritPhpDoc($class, 'properties');
        $this->inheritPhpDoc($class, 'methods');

        return;
        yield;
    }

    private function inheritPhpDoc(ResolvedClassLike $class, string $kind)
    {
        /** @var Property|Method $member */
        foreach ($class->$kind as $name => $member) {
            if ($member->docComment === null ||
                preg_match('~\{@inheritDoc\}~i', $member->docComment->text)
            ) {
                $inheritedDocComment = $this->findDocComment($class, $kind, $name);
                if (($member->docComment->text ?? null) !== ($inheritedDocComment->text ?? null)) {
                    $newMember = clone $member;
                    $newMember->docComment = $inheritedDocComment;
                    $class->$kind[$name] = $newMember;
                }
            }
        }
    }

    /**
     * @return DocComment|null
     */
    private function findDocComment(ResolvedClassLike $class, string $kind, string $name)
    {
        /** @var ResolvedClassLike $parent */
        foreach (array_merge($class->parentClass ? [$class->parentClass] : [], $class->interfaces) as $parent) {
            /** @var Property|Method|null $member */
            $member = $parent->$kind[$name] ?? null;
            if ($member !== null && $member->accessibility !== ClassLike::M_PRIVATE && $member->docComment !== null) {
                $docComment = clone $member->docComment;
                $docComment->nameContext = $docComment->nameContext ?? $member->nameContext;

                return $docComment;
            }
        }

        return null;
    }
}
