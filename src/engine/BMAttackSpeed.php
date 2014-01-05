<?php

class BMAttackSpeed extends BMAttack {
    public $type = 'Speed';

    public function validate_attack($game, array $attackers, array $defenders) {
        if (1 != count($attackers) || count($defenders) < 1) {
            return FALSE;
        }

        if ($this->has_disabled_attackers($attackers)) {
            return FALSE;
        }

        $attacker = $attackers[0];
        $doesAttackerHaveSkill = $attacker->has_skill($this->type);

        $defenderSum = 0;
        foreach ($defenders as $defender) {
            $defenderSum += $defender->value;
        }
        $areValuesEqual = $attacker->value == $defenderSum;

        $canAttackerPerformThisAttack =
            $attacker->is_valid_attacker($this->type, $attackers);
        $areDefendersValidTargetsForThisAttack = TRUE;
        foreach ($defenders as $defender) {
            if (!($defender->is_valid_target($this->type, $defenders))) {
                $areDefendersValidTargetsForThisAttack = FALSE;
                break;
            }
        }

        return ($doesAttackerHaveSkill &&
                $areValuesEqual &&
                $canAttackerPerformThisAttack &&
                $areDefendersValidTargetsForThisAttack);
    }

    public function find_attack($game) {
        return $this->search_onevmany(
            $game,
            $game->attackerAllDieArray,
            $game->defenderAllDieArray
        );
    }
}
