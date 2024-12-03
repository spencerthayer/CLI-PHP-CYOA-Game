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
            write_debug_log("Healing applied", [
                'old_hp' => $oldHP,
                'amount_healed' => $amount,
                'new_hp' => $this->currentHP,
                'max_hp' => $maxHP
            ]);
        }
        
        return $this->currentHP - $oldHP; // Return actual amount healed
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

        $result = [
            'success' => $success,
            'roll' => $roll,
            'modifier' => $modifier,
            'proficiency' => $proficiencyBonus,
            'total' => $total,
            'difficulty' => $difficulty,
            'attribute_score' => $this->attributes[$attribute],
            'details' => sprintf(
                "Rolled %d + %d (modifier) + %d (proficiency) = %d vs DC %d",
                $roll, $modifier, $proficiencyBonus, $total, $difficulty
            )
        ];

        if ($this->debug) {
            write_debug_log("🎲 Skill Check Result", [
                'type' => 'skill_check',
                'attribute' => $attribute,
                'base_score' => $this->attributes[$attribute],
                'roll' => [
                    'die' => 'd20',
                    'result' => $roll,
                    'modifier' => $modifier,
                    'proficiency' => $proficiencyBonus,
                    'total' => $total
                ],
                'difficulty' => $difficulty,
                'success' => $success,
                'narrative_impact' => $success ? 
                    "Character successfully uses their {$attribute} (DC {$difficulty})" :
                    "Character fails to use their {$attribute} effectively (DC {$difficulty})"
            ]);
        }

        return $result;
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

        $result = [
            'success' => $success,
            'roll' => $roll,
            'modifier' => $modifier,
            'total' => $total,
            'difficulty' => $difficulty,
            'save_type' => $type,
            'attribute' => $attribute,
            'attribute_score' => $this->attributes[$attribute],
            'details' => sprintf(
                "Rolled %d + %d (modifier) = %d vs DC %d",
                $roll, $modifier, $total, $difficulty
            )
        ];

        if ($this->debug) {
            write_debug_log("🎲 Saving Throw Result", [
                'type' => 'saving_throw',
                'save_type' => $type,
                'attribute' => $attribute,
                'base_score' => $this->attributes[$attribute],
                'roll' => [
                    'die' => 'd20',
                    'result' => $roll,
                    'modifier' => $modifier,
                    'total' => $total
                ],
                'difficulty' => $difficulty,
                'success' => $success,
                'narrative_impact' => $success ? 
                    "Character successfully resists the {$type} effect (DC {$difficulty})" :
                    "Character fails to resist the {$type} effect (DC {$difficulty})"
            ]);
        }

        return $result;
    }

    public function sanityCheck($difficulty = 15)
    {
        $result = $this->skillCheck('Sanity', $difficulty);
        
        if (!$result['success']) {
            $sanityLoss = rand(1, 4); // 1d4 sanity loss on failure
            $oldSanity = $this->currentSanity;
            $this->currentSanity = max(0, $this->currentSanity - $sanityLoss);
            
            if ($this->debug) {
                write_debug_log("Failed Sanity Check", [
                    'old_sanity' => $oldSanity,
                    'sanity_loss' => $sanityLoss,
                    'new_sanity' => $this->currentSanity
                ]);
            }
            
            $result['sanityLoss'] = $sanityLoss;
            $result['currentSanity'] = $this->currentSanity;
        }
        
        return $result;
    }

    // Attribute Modification Methods
    public function modifyAttribute($attribute, $amount)
    {
        if (!isset($this->attributes[$attribute])) {
            if ($this->debug) {
                write_debug_log("Invalid attribute modification attempt", [
                    'attribute' => $attribute,
                    'amount' => $amount
                ]);
            }
            return false;
        }

        $oldValue = $this->attributes[$attribute];
        $this->attributes[$attribute] = max(0, min(20, $oldValue + $amount)); // Cap between 0 and 20
        
        if ($this->debug) {
            write_debug_log("Attribute modified", [
                'attribute' => $attribute,
                'old_value' => $oldValue,
                'change' => $amount,
                'new_value' => $this->attributes[$attribute]
            ]);
        }
        
        return true;
    }

    public function gainExperience($amount)
    {
        $oldLevel = $this->level;
        $this->level += $amount;
        
        if ($this->debug) {
            write_debug_log("Experience gained", [
                'old_level' => $oldLevel,
                'amount_gained' => $amount,
                'new_level' => $this->level
            ]);
        }
        
        return true;
    }

    public function restoreSanity($amount)
    {
        $oldSanity = $this->currentSanity;
        $maxSanity = $this->attributes['Sanity'];
        $this->currentSanity = min($maxSanity, $this->currentSanity + $amount);
        
        if ($this->debug) {
            write_debug_log("Sanity restored", [
                'old_sanity' => $oldSanity,
                'amount_restored' => $amount,
                'new_sanity' => $this->currentSanity,
                'max_sanity' => $maxSanity
            ]);
        }
        
        return $this->currentSanity - $oldSanity; // Return actual amount restored
    }

    // Getters and Setters
    public function getAttribute($attribute)
    {
        return $this->attributes[$attribute] ?? null;
    }

    public function setAttribute($attribute, $value)
    {
        if (array_key_exists($attribute, $this->attributes)) {
            $this->attributes[$attribute] = max(1, min(20, $value)); // Limit attributes between 1 and 20
            return true;
        }
        return false;
    }

    public function getLevel() 
    { 
        return $this->level; 
    }

    public function setLevel($level) 
    { 
        $this->level = max(1, $level); 
    }

    public function getCurrentHP() 
    { 
        return $this->currentHP; 
    }

    public function getCurrentFP() 
    { 
        return $this->currentFP; 
    }

    public function getCurrentStamina() 
    { 
        return $this->currentStamina; 
    }

    public function getCurrentSanity() 
    { 
        return $this->currentSanity; 
    }

    // State Management
    public function getStats()
    {
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
            'level' => $this->level
        ];
    }

    public function getState()
    {
        return $this->getStats();
    }

    public function loadState($state)
    {
        if (isset($state['attributes'])) {
            $this->attributes = $state['attributes'];
        }
        if (isset($state['level'])) {
            $this->level = $state['level'];
        }
        if (isset($state['currentHP'])) {
            $this->currentHP = $state['currentHP'];
        }
        if (isset($state['currentSanity'])) {
            $this->currentSanity = $state['currentSanity'];
        }
        
        if ($this->debug) {
            write_debug_log("Character state loaded", [
                'attributes' => $this->attributes,
                'level' => $this->level,
                'hp' => $this->currentHP,
                'sanity' => $this->currentSanity
            ]);
        }
    }
}
