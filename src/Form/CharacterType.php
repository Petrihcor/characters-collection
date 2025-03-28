<?php

namespace App\Form;

use App\Entity\Character;
use App\Entity\League;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CharacterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('file', FileType::class, [
                'mapped' => false,
                'required' => true,
            ])
            ->add('intelligence')
            ->add('strength')
            ->add('agility')
            ->add('specialPowers')
            ->add('fightingSkills')
            ->add('description')
            ->add('league', EntityType::class, [
                'class' => League::class,
                'choice_label' => 'id',
            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Character::class,
        ]);
    }
}
