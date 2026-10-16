<?php

namespace App\Services;

use App\Enums\ColorSwatchRole;
use App\Enums\OptionRuleAction;
use App\Models\OptionRule;
use App\Services\Studio\CompatibilityResult;
use App\Services\Studio\StudioConfiguration;

class CompatibilityValidator
{
    public function validate(StudioConfiguration $config, bool $strict = false): CompatibilityResult
    {
        $violations = [];
        $warnings = [];
        $blockedOptions = [];
        $requiredOptions = [];

        $selected = $config->selectedOptions();
        $selectedKeys = $this->keys($selected);
        $selectedByType = collect($selected)->groupBy('type');

        $this->appendStructuralIssues($config, $strict, $violations, $warnings);

        foreach ($config->unknown as $unknown) {
            $type = match ($unknown['field']) {
                'base_item_id' => 'base_item',
                'mold_id' => 'mold',
                'mood_id' => 'mood',
                'technique_id' => 'technique',
                'base_color_id', 'accent_color_id' => 'color_swatch',
                'font_id' => 'font',
                'text_color_id' => 'text_color',
                'insert_ids' => 'insert',
                default => 'option',
            };

            $label = str_replace('_', ' ', $type);
            $violations[] = $this->structural($type, is_numeric($unknown['id']) ? (int) $unknown['id'] : null, 'Unknown '.$label.'.');
        }

        foreach (OptionRule::query()->get() as $rule) {
            $subjectKey = $rule->subject_type.':'.$rule->subject_id;
            $target = [
                'type' => $rule->target_type,
                'id' => $rule->target_id,
                'message' => $rule->message,
                'rule' => $rule->rule->value,
            ];

            if (! isset($selectedKeys[$subjectKey])) {
                continue;
            }

            if ($rule->rule === OptionRuleAction::Blocks) {
                $blockedOptions[] = $target;

                if (isset($selectedKeys[$rule->target_type.':'.$rule->target_id])) {
                    $violations[] = $this->issue($rule, 'This combination is not available.');
                }

                continue;
            }

            $requiredOptions[] = $target;
            $targetSelected = isset($selectedKeys[$rule->target_type.':'.$rule->target_id]);
            $typeAlreadyChosen = $selectedByType->has($rule->target_type);

            if ($targetSelected) {
                continue;
            }

            if ($typeAlreadyChosen || $strict) {
                $violations[] = $this->issue($rule, 'A required option is missing from this configuration.');

                continue;
            }

            $warnings[] = [
                'rule' => $rule->rule->value,
                'message' => $rule->message,
                'subject_type' => $rule->subject_type,
                'subject_id' => $rule->subject_id,
                'target_type' => $rule->target_type,
                'target_id' => $rule->target_id,
            ];
        }

        $blockedOptions = $this->uniqueOptions($blockedOptions);
        $requiredOptions = $this->uniqueOptions($requiredOptions);

        return new CompatibilityResult(
            allowed: $violations === [],
            violations: $violations,
            blockedOptions: $blockedOptions,
            requiredOptions: $requiredOptions,
            warnings: $warnings,
        );
    }

    /**
     * @param  list<array{type: string, id: int}>  $selected
     * @return array<string, true>
     */
    private function keys(array $selected): array
    {
        $keys = [];

        foreach ($selected as $option) {
            $keys[$option['type'].':'.$option['id']] = true;
        }

        return $keys;
    }

    /**
     * @param  list<array<string, mixed>>  $violations
     * @param  list<array<string, mixed>>  $warnings
     */
    private function appendStructuralIssues(
        StudioConfiguration $config,
        bool $strict,
        array &$violations,
        array &$warnings,
    ): void {
        if ($config->baseItem && $config->baseItem->is_active === false) {
            $violations[] = $this->structural('base_item', $config->baseItem->id, 'This item is not currently offered.');
        }

        if ($config->mood && $config->mood->is_active === false) {
            $violations[] = $this->structural('mood', $config->mood->id, 'That mood is not currently offered.');
        }

        if ($strict && $config->baseItem === null) {
            $violations[] = $this->structural('base_item', null, 'Choose an item to quote.');
        }

        if ($strict && $config->mold === null) {
            $violations[] = $this->structural('mold', null, 'Choose a mold.');
        }

        if ($strict && $config->mood === null) {
            $violations[] = $this->structural('mood', null, 'Choose a mood.');
        }

        if ($strict && $config->technique === null) {
            $violations[] = $this->structural('technique', null, 'Choose a technique.');
        }

        if ($strict && $config->baseColor === null) {
            $violations[] = $this->structural('color_swatch', null, 'Choose a base color.');
        }

        if ($config->mold && $config->baseItem && $config->mold->base_item_id !== $config->baseItem->id) {
            $violations[] = $this->structural(
                'mold',
                $config->mold->id,
                'That mold does not belong to the selected item.',
            );
        }

        if ($config->baseColor && $config->baseColor->role !== ColorSwatchRole::Base) {
            $violations[] = $this->structural('color_swatch', $config->baseColor->id, 'Choose a base pigment, not an accent.');
        }

        if ($config->accentColor && $config->accentColor->role !== ColorSwatchRole::Accent) {
            $violations[] = $this->structural('color_swatch', $config->accentColor->id, 'Choose an accent pigment, not a base color.');
        }

        if ($config->baseColor && $config->baseColor->is_available === false) {
            $violations[] = $this->structural('color_swatch', $config->baseColor->id, 'That base pigment is out of stock.');
        }

        if ($config->accentColor && $config->accentColor->is_available === false) {
            $violations[] = $this->structural('color_swatch', $config->accentColor->id, 'That accent pigment is out of stock.');
        }

        foreach ($config->inserts as $insert) {
            if ($insert->is_available === false) {
                $violations[] = $this->structural('insert', $insert->id, $insert->name.' is not available right now.');
            }

            if ($config->mold && (float) $config->mold->depth_mm < (float) $insert->min_depth_mm) {
                $violations[] = $this->structural(
                    'insert',
                    $insert->id,
                    $insert->name.' needs at least '.$insert->min_depth_mm.'mm of depth.',
                );
            }
        }

        if ($config->technique?->requires_accent) {
            if ($config->accentColor === null) {
                $message = $config->technique->name.' needs an accent color.';
                if ($strict) {
                    $violations[] = $this->structural('technique', $config->technique->id, $message);
                } else {
                    $warnings[] = $this->structural('technique', $config->technique->id, $message);
                }
            }
        }

        if ($config->hasPersonalization() && $config->baseItem) {
            $length = mb_strlen($config->personalizationText ?? '');
            if ($length > $config->baseItem->max_text_chars) {
                $violations[] = $this->structural(
                    'base_item',
                    $config->baseItem->id,
                    'Personalization can be at most '.$config->baseItem->max_text_chars.' characters.',
                );
            }
        }

        if ($strict && $config->hasPersonalization() && ($config->font === null || $config->textColor === null)) {
            $violations[] = $this->structural('font', $config->font?->id, 'Personalization needs a font and a text color.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(OptionRule $rule, string $fallback): array
    {
        return [
            'rule' => $rule->rule->value,
            'message' => $rule->message !== '' ? $rule->message : $fallback,
            'subject_type' => $rule->subject_type,
            'subject_id' => $rule->subject_id,
            'target_type' => $rule->target_type,
            'target_id' => $rule->target_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structural(string $type, ?int $id, string $message): array
    {
        return [
            'rule' => 'invalid',
            'message' => $message,
            'subject_type' => $type,
            'subject_id' => $id,
            'target_type' => null,
            'target_id' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    private function uniqueOptions(array $options): array
    {
        return collect($options)
            ->unique(fn (array $option) => $option['type'].':'.$option['id'])
            ->values()
            ->all();
    }
}
