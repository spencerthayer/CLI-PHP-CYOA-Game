<?php

namespace App;

class CharacterStats
{
    // Base Attributes
    private $attributes = [
        'Vitality' => 10,
        'Willpower' => 10,
        'Endurance' => 10,
        'Strength' => 10,
        'Dexterity' => 10,
        'Intellect' => 10,
        'Faith' => 10,
        'Luck' => 10,
        'Sanity' => 10,
    ];

    private $level = 1;
    private $baseHP = 10;
    private $baseFP = 5;
    private $baseStamina = 10;
    private $currentHP;
    private $currentFP;
    private $currentStamina;
    private $currentSanity;
    private $debug = false;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
        $this->currentHP = $this->calculateHP();
        $this->currentFP = $this->calculateFP();
        $this->currentStamina = $this->calculateStamina();
        $this->currentSanity = $this->attributes['Sanity'];

        if ($this->debug) {
            write_debug_log("CharacterStats initialized", [
                'attributes' => $this->attributes,
                'derived_stats' => [
                    'HP' => $this->currentHP,
                    'FP' => $this->currentFP,
                    'Stamina' => $this->currentStamina,
                    'Sanity' => $this->currentSanity
                ]
            ]);
        }
    }

    // Getters and Setters
    public function getAttribute($name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function setAttribute($name, $value)
    {
        if (array_key_exists($name, $this->attributes)) {
            $this->attributes[$name] = max(1, min(20, $value)); // Limit attributes between 1 and 20
            return true;
        }
        return false;
    }

    public function getLevel() { return $this->level; }
    public function setLevel($level) { $this->level = max(1, $level); }

    public function getCurrentHP() { return $this->currentHP; }
    public function getCurrentFP() { return $this->currentFP; }
    public function getCurrentStamina() { return $this->currentStamina; }
    public function getCurrentSanity() { return $this->currentSanity; }

    // Core Calculations
    private function calculateModifier($score)
    {
        $modifier = floor(($score - 10) / 2);
        if ($this->debug) {
            write_debug_log("Calculated modifier", [
                'score' => $score,
                'modifier' => $modifier
            ]);
        }
        return $modifier;
    }

    public function calculateHP()
    {
        return $this->baseHP + ($this->calculateModifier($this->attributes['Vitality']) * $this->level);
    }

    public function calculateFP()
    {
        return $this->baseFP + ($this->calculateModifier($this->attributes['Willpower']) * $this->level);
    }

    public function calculateStamina()
    {
        return $this->baseStamina + ($this->calculateModifier($this->attributes['Endurance']) * $this->level);
    }

    public function calculateAC($armorBonus = 0)
    {
        return 10 + $this->calculateModifier($this->attributes['Dexterity']) + $armorBonus;
    }

    // Game Mechanics
    public function takeDamage($amount)
    {
        $oldHP = $this->currentHP;
        $this->currentHP = max(0, $this->currentHP - $amount);

        if ($this->debug) {
            write_debug_log("Taking Damage", [
                'damage_amount' => $amount,
                'old_hp' => $oldHP,
                'new_hp' => $this->currentHP
            ]);
        }

        return $this->currentHP;
    }

    public function heal($amount)
    {
        $oldHP = $this->currentHP;
        $maxHP = $this->calculateHP();
        $this->currentHP = min($maxHP, $this->currentHP + $amount);

        if ($this->debug) {
            write_debug_log("Healing", [
                'heal_amount' => $amount,
                'old_hp' => $oldHP,
                'new_hp' => $this->currentHP,
                'max_hp' => $maxHP
            ]);
        }

        return $this->currentHP;
    }

    public function useFP($amount)
    {
        if ($this->currentFP >= $amount) {
            $this->currentFP -= $amount;
            return true;
        }
        return false;
    }

    public function useStamina($amount)
    {
        if ($this->currentStamina >= $amount) {
            $this->currentStamina -= $amount;
            return true;
        }
        return false;
    }

    public function rest()
    {
        $this->currentHP = $this->calculateHP();
        $this->currentFP = $this->calculateFP();
        $this->currentStamina = $this->calculateStamina();
    }

    // Skill Checks and Saves
    public function skillCheck($attribute, $difficulty = 15, $proficiencyBonus = 0)
    {
        if (!array_key_exists($attribute, $this->attributes)) {
            throw new \Exception("Invalid attribute: $attribute");
        }

        $roll = rand(1, 20);
        $modifier = $this->calculateModifier($this->attributes[$attribute]);
        $total = $roll + $modifier + $proficiencyBonus;
        $success = $total >= $difficulty;

        if ($this->debug) {
            write_debug_log("Skill Check", [
                'attribute' => $attribute,
                'difficulty' => $difficulty,
                'roll' => $roll,
                'modifier' => $modifier,
                'proficiency' => $proficiencyBonus,
                'total' => $total,
                'success' => $success
            ]);
        }

        return [
            'success' => $success,
            'roll' => $roll,
            'total' => $total,
            'details' => "Rolled $roll + $modifier (modifier) + $proficiencyBonus (proficiency) = $total"
        ];
    }

    public function savingThrow($type, $difficulty = 15)
    {
        $attribute = '';
        switch ($type) {
            case 'Fortitude':
                $attribute = 'Vitality';
                break;
            case 'Reflex':
                $attribute = 'Dexterity';
                break;
            case 'Will':
                $attribute = 'Willpower';
                break;
            default:
                throw new \Exception("Invalid saving throw type: $type");
        }

        $roll = rand(1, 20);
        $modifier = $this->calculateModifier($this->attributes[$attribute]);
        $total = $roll + $modifier;
        $success = $total >= $difficulty;

        if ($this->debug) {
            write_debug_log("Saving Throw", [
                'type' => $type,
                'attribute' => $attribute,
                'difficulty' => $difficulty,
                'roll' => $roll,
                'modifier' => $modifier,
                'total' => $total,
                'success' => $success
            ]);
        }

        return [
            'success' => $success,
            'roll' => $roll,
            'total' => $total,
            'details' => "Rolled $roll + $modifier (modifier) = $total"
        ];
    }

    public function sanityCheck($difficulty = 15)
    {
        $roll = rand(1, 20);
        $modifier = $this->calculateModifier($this->attributes['Sanity']);
        $total = $roll + $modifier;
        $success = $total >= $difficulty;
        $sanityLoss = $success ? 0 : rand(1, 4);

        if (!$success) {
            $this->currentSanity = max(0, $this->currentSanity - $sanityLoss);
        }

        if ($this->debug) {
            write_debug_log("Sanity Check", [
                'difficulty' => $difficulty,
                'roll' => $roll,
                'modifier' => $modifier,
                'total' => $total,
                'success' => $success,
                'sanity_loss' => $sanityLoss,
                'current_sanity' => $this->currentSanity
            ]);
        }

        return [
            'success' => $success,
            'roll' => $roll,
            'total' => $total,
            'sanityLoss' => $sanityLoss,
            'currentSanity' => $this->currentSanity,
            'details' => "Rolled $roll + $modifier (modifier) = $total"
        ];
    }

    // State Management
    public function getStats() {
        return [
            'Vitality' => $this->attributes['Vitality'],
            'Willpower' => $this->attributes['Willpower'],
            'Endurance' => $this->attributes['Endurance'],
            'Strength' => $this->attributes['Strength'],
            'Dexterity' => $this->attributes['Dexterity'],
            'Intellect' => $this->attributes['Intellect'],
            'Faith' => $this->attributes['Faith'],
            'Luck' => $this->attributes['Luck'],
            'Sanity' => $this->attributes['Sanity'],
            'hp' => [
                'current' => $this->currentHP,
                'max' => $this->calculateHP()
            ],
            'sanity' => [
                'current' => $this->currentSanity,
                'max' => $this->attributes['Sanity']
            ],
            'level' => $this->level,
        ];
    }

    public function getState()
    {
        return $this->getStats();
    }

    public function loadState($state)
    {
        $this->attributes = $state['attributes'] ?? $this->attributes;
        $this->level = $state['level'] ?? $this->level;
        $this->currentHP = $state['currentHP'] ?? $this->calculateHP();
        $this->currentFP = $state['currentFP'] ?? $this->calculateFP();
        $this->currentStamina = $state['currentStamina'] ?? $this->calculateStamina();
        $this->currentSanity = $state['currentSanity'] ?? $this->attributes['Sanity'];

        if ($this->debug) {
            write_debug_log("Loaded Character State", [
                'loaded_state' => $state,
                'current_state' => $this->getState()
            ]);
        }
    }
}
