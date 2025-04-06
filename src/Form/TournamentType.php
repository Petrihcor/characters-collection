<?php

namespace App\Form;

use App\Entity\League;
use App\Entity\Tournament;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TournamentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('number_participants')
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Простое сравнивание' => 'classic',
                    'Сравнивание с вероятностью' => 'logistic'
                ],
                'placeholder' => 'Выберите тип турнира',
            ])
            ->add('stats', ChoiceType::class, [
                'choices' => [
                    'Интеллект' => 'intelligence',
                    'Сила' => 'strength',
                    'Ловкость' => 'agility',
                    'Особые умения' => 'specialPowers',
                    'Бойцовские навыки' => 'fightingSkills',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tournament::class,
        ]);
    }
}
