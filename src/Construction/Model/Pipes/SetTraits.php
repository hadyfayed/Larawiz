<?php

namespace Larawiz\Larawiz\Construction\Model\Pipes;

use Closure;
use Illuminate\Support\Arr;
use Larawiz\Larawiz\Construction\Model\ModelConstruction;

class SetTraits
{
    /**
     * Handle the model construction.
     *
     * @param  \Larawiz\Larawiz\Construction\Model\ModelConstruction  $construction
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(ModelConstruction $construction, Closure $next)
    {
        if ($construction->model->quickTraits->isNotEmpty()) {
            foreach ($construction->model->quickTraits as $trait) {

                $namespace = $trait->external ? $trait->namespace : $trait->fullNamespace();

                Arr::first($construction->file->getNamespaces())->addUse($namespace);
                if($namespace == 'Spatie\Sluggable\HasSlug'){
                    $SpatieSluggableMethod = "return SlugOptions::create()". "\n";
                    $SpatieSluggableMethod .= "\t"."->generateSlugsFrom('".Arr::first($construction->model->fillable)."')". "\n";
                    $SpatieSluggableMethod .= "\t"."->saveSlugsTo('slug')". "\n";
                    $SpatieSluggableMethod .= "\t"."->usingSeparator('_');". "\n";
                    Arr::first($construction->file->getNamespaces())->addUse('Spatie\Sluggable\SlugOptions');
                    $construction->class->addMethod('getSlugOptions')
                        ->addBody($SpatieSluggableMethod)
                        ->addComment(" ")
                        ->addComment("@return SlugOptions")
                        ->setReturnType('Spatie\Sluggable\SlugOptions');
                }
                $construction->class->addTrait($namespace);
            }
        }

        return $next($construction);
    }
}
