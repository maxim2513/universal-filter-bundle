<?php
/**
 * Created by IntelliJ IDEA.
 * User: work
 * Date: 7/2/17
 * Time: 3:07 PM
 */

namespace MaximMV\Bundle\UniversalFilterBundle\DependencyInjection\Compiler;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CustomFilterCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if(false == $container->hasDefinition('universal_filter.filter_chain')){
            return;
        }

        $definition = $container->getDefinition(
            'universal_filter.filter_chain'
        );

        $taggedService = $container->findTaggedServiceIds(
            'universal_filter.filter'
        );

        foreach ($taggedService as $id => $tags){
            foreach ($tags as $attributes){
                $definition->addMethodCall('addFilter',[
                    new Reference($id),
                    $attributes['type']
                ]);
            }
        }
    }

}