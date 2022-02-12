<?php

namespace Larawiz\Larawiz\Construction\Model\Pipes;

use Closure;
use Larawiz\Larawiz\Construction\Model\ModelConstruction;
use Larawiz\Larawiz\Lexing\Code\Method;
use Larawiz\Larawiz\Lexing\Database\Relations\BaseRelation;
use Larawiz\Larawiz\Lexing\Database\Relations\BelongsTo;
use Larawiz\Larawiz\Lexing\Database\Relations\MorphTo;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class SetRelations
{
    /**
     * Handle the model construction
     *
     * @param  \Larawiz\Larawiz\Construction\Model\ModelConstruction  $construction
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(ModelConstruction $construction, Closure $next)
    {
        if ($construction->model->relations->isNotEmpty()) {

            foreach ($construction->model->relations as $relation) {

                if ($relation instanceof MorphTo) {
                    collect(json_decode($relation->models->map->fullNamespace()))->each(function ($item, $key) use($construction){
                        $construction->namespace->addUse($item);
                    });
                    $construction->namespace->addUse('Illuminate\Database\Eloquent\Relations\MorphTo');

                    $this->setMorphToComment($construction->class, $relation);
                    $this->setMorphToRelation($construction->class, $relation);
                } elseif($relation instanceof BelongsTo) {
                    $construction->namespace->addUse($relation->model->fullNamespace());
                    $construction->namespace->addUse('Illuminate\Database\Eloquent\Relations\BelongsTo');

                    $this->setBelongsToComment($construction->class, $relation);
                    $this->setBelongsToRelation($construction->class, $relation);
                }
                else {
                    $construction->namespace->addUse($relation->model->fullNamespace());
                    $construction->namespace->addUse($relation->class());
                    $construction->namespace->addUse('Illuminate\Database\Eloquent\Collection');
                    $this->setClassComment($construction->class, $relation);
                    $this->setRelation($construction->namespace, $construction->class, $relation)
                        ->setReturnType($relation->class())
                        ->addComment(str($relation->type)->headline().' '.str($relation->model->getRelativeNamespaceWithoutModel())->plural())
                        ->addComment("@return ".str($relation->class())->afterLast('\\'));
                }
            }

            $construction->class->addComment('');
        }

        return $next($construction);
    }

    /**
     * Sets the MorphTo comment.
     *
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\MorphTo  $relation
     */
    protected function setMorphToComment(ClassType $class, MorphTo $relation)
    {
        $start = '@property-read ';

        if (! $relation->usesWithDefault() && $relation->isNullable()) {
            $start .= 'null|';
        }

        $start .= $relation->models->map->getRelativeNamespaceWithoutModel()->implode('|');

        $class->addComment("{$start} \${$relation->name}");
    }

    /**
     * Sets the "morphTo" Relation.
     *
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\MorphTo  $relation
     */
    protected function setMorphToRelation(ClassType $class, MorphTo $relation)
    {
        $class->addMethod($relation->name)
            ->setPublic()
            ->setBody('return $this->' . Method::methodsToString($relation->relationMethods()) . ';')
            ->setReturnType('\Illuminate\Database\Eloquent\Relations\MorphTo')
            ->addComment("Morph To ". collect(json_decode($relation->models->map->getRelativeNamespaceWithoutModel()))->implode(' & '))
            ->addComment("@return MorphTo");
    }

    /**
     * Sets the "belongsTo" comment.
     *
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\BelongsTo  $relation
     */
    protected function setBelongsToComment(ClassType $class, BelongsTo $relation)
    {
        $start = '@property-read ';

        if (! $relation->usesWithDefault() && $relation->isNullable()) {
            $start .= 'null|';
        }

        $start .= $relation->model->getRelativeNamespaceWithoutModel();

        $class->addComment("{$start} \${$relation->name}");
    }

    /**
     * Sets the "belongsTo" relation.
     *
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\BelongsTo  $relation
     */
    protected function setBelongsToRelation(ClassType $class, BelongsTo $relation)
    {
        $class->addMethod($relation->name)
            ->setPublic()
            ->setBody('return $this->' . Method::methodsToString($relation->relationMethods()) . ';')
            ->setReturnType('Illuminate\Database\Eloquent\Relations\BelongsTo')
            ->addComment("Belongs To {$relation->model->getRelativeNamespaceWithoutModel()}")
            ->addComment("@return BelongsTo");
    }

    /**
     * Sets the Relation PHPDoc.
     *
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\BaseRelation  $relation
     */
    protected function setClassComment(ClassType $class, BaseRelation $relation)
    {
        $start = '@property-read ';

        if (! $relation->returnsCollection() && ! $relation->usesWithDefault()) {
            $start .= 'null|';
        }

        if ($relation->returnsCollection()) {
            $start .= "Collection|{$relation->model->getRelativeNamespaceWithoutModel()}";
        }
        else {
            $start .= $relation->model->fullRootNamespace();
        }

        $class->addComment("{$start} \${$relation->name}");
    }

    /**
     * Sets the relation as a method.
     *
     * @param  \Nette\PhpGenerator\PhpNamespace  $namespace
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\BaseRelation  $relation
     * @return \Nette\PhpGenerator\Method
     */
    protected function setRelation(PhpNamespace $namespace, ClassType $class, BaseRelation $relation)
    {
        if ($relation->needsPivotTable()) {
            return $this->setPivotRelation($class, $relation);
        }

        if ($relation->isThrough()) {
            return $this->setThroughRelation($namespace, $class, $relation);
        }

        return $this->setNormalRelation($class, $relation);
    }

    /**
     * Sets a Pivot relation to the model.
     *
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\BaseRelation  $relation
     * @return \Nette\PhpGenerator\Method
     */
    protected function setPivotRelation(ClassType $class, BaseRelation $relation)
    {
        return $class->addMethod($relation->name)
            ->setPublic()
            ->setBody('return $this->' . Method::methodsToString($relation->methods) . ';');
    }

    /**
     * Sets a through relation method body.
     *
     * @param  \Nette\PhpGenerator\PhpNamespace  $namespace
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\BaseRelation  $relation
     * @return \Nette\PhpGenerator\Method
     */
    protected function setThroughRelation(PhpNamespace $namespace, ClassType $class, BaseRelation $relation)
    {
        $namespace->addUse($relation->through->fullNamespace());

        return $class->addMethod($relation->name)
            ->setPublic()
            ->setBody('return $this->' . Method::methodsToString($relation->methods) . ';');
    }

    /**
     * Sets the normal relation.
     *
     * @param  \Nette\PhpGenerator\ClassType  $class
     * @param  \Larawiz\Larawiz\Lexing\Database\Relations\BaseRelation  $relation
     * @return \Nette\PhpGenerator\Method
     */
    protected function setNormalRelation(ClassType $class, BaseRelation $relation)
    {
        return $class->addMethod($relation->name)
            ->setPublic()
            ->setBody('return $this->' . Method::methodsToString($relation->methods) . ';');
    }
}
