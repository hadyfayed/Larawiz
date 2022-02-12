<?php

namespace Larawiz\Larawiz\Construction\Model\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Arr;
use Larawiz\Larawiz\Construction\Model\ModelConstruction;

class SetsFactoryTrait
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
        // If the model is using factories, we will add it to the class.
        if ($construction->model->useFactory) {
            Arr::first($construction->file->getNamespaces())->addUse(HasFactory::class);
            Arr::first($construction->file->getNamespaces())->addUse("\Database\Factories\\{$construction->model->class}Factory");
            $construction->class->addTrait(HasFactory::class);
            // Add a PHPDoc to override the Factory class for the model factory.
            $construction->class->addComment(
                "@method static {$construction->model->class}Factory factory(int|array ...\$parameters)"
            );

            $construction->class->addComment('');
        }

        return $next($construction);
    }
}
