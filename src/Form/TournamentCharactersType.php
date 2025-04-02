<?php

namespace App\Form;

use App\Entity\Character;
use App\Entity\Tournament;
use App\Entity\TournamentCharacter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TournamentCharactersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('character', EntityType::class, [
                'class' => Character::class,
                'choice_label' => 'name',
            ])
            ->add('submit', SubmitType::class, ['label' => 'Добавить'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TournamentCharacter::class,
        ]);
    }
}
