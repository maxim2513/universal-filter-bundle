<?php

namespace MaximMV\Bundle\UniversalFilterBundle;

use MaximMV\Bundle\UniversalFilterBundle\DependencyInjection\Compiler\CustomFilterCompilerPass;
use MaximMV\Bundle\UniversalFilterBundle\DependencyInjection\UniversalFilterExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class UniversalFilterBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new CustomFilterCompilerPass());
    }

    public function getContainerExtension()
    {
        return new UniversalFilterExtension();
    }
}
