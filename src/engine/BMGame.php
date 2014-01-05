<?php

/**
 * BMGame: current status of a game
 *
 * @author james
 *
 * @property      int   $gameId                  Game ID number in the database
 * @property      array $playerIdArray           Array of player IDs
 * @property-read array $nPlayers                Number of players in the game
 * @property-read int   $roundNumber;            Current round number
 * @property      int   $turnNumberInRound;      Current turn number in current round
 * @property      int   $activePlayerIdx         Index of the active player in playerIdxArray
 * @property      int   $playerWithInitiativeIdx Index of the player who won initiative
 * @property      array $buttonArray             Buttons for all players
 * @property-read array $activeDieArrayArray     Active dice for all players
 * @property      array $attack                  array('attackerPlayerIdx',<br>
                                                       'defenderPlayerIdx',<br>
                                                       'attackerAttackDieIdxArray',<br>
                                                       'defenderAttackDieIdxArray',<br>
                                                       'attackType')
 * @property-read int   $attackerPlayerIdx       Index in playerIdxArray of the attacker
 * @property-read int   $defenderPlayerIdx       Index in playerIdxArray of the defender
 * @property-read array $attackerAllDieArray     Array of all attacker's dice
 * @property-read array $defenderAllDieArray     Array of all defender's dice
 * @property-read array $attackerAttackDieArray  Array of attacker's dice used in attack
 * @property-read array $defenderAttackDieArray  Array of defender's dice used in attack
 * @property      array $auxiliaryDieDecisionArrayArray Array storing player decisions about auxiliary dice
 * @property-read int   $nRecentPasses           Number of consecutive passes
 * @property-read array $capturedDieArrayArray   Captured dice for all players
 * @property-read array $roundScoreArray         Current points score in this round
 * @property-read array $gameScoreArrayArray     Number of games W/L/D for all players
 * @property-read array $isPrevRoundWinnerArray  Boolean array whether each player won the previous round
 * @property      int   $maxWins                 The game ends when a player has this many wins
 * @property-read BMGameState $gameState         Current game state as a BMGameState enum
 * @property      array $waitingOnActionArray    Boolean array whether each player needs to perform an action
 * @property      array $autopassArray           Boolean array whether each player has enabled autopass
 * @property      array $actionLog               Game actions taken by this BMGame instance
 * @property      array $chat                    A chat message submitted by the active player
 * @property-read string $message                Message to be passed to the GUI
 * @property      array $swingRequestArrayArray  Swing requests for all players
 * @property      array $swingValueArrayArray    Swing values for all players
 * @property    boolean $allValuesSpecified      Boolean flag of whether all swing values have been specified
 *
 */
class BMGame {
    // properties -- all accessible, but written as private to enable the use of
    //               getters and setters
    private $gameId;                // game ID number in the database
    private $playerIdArray;         // array of player IDs
    private $nPlayers;              // number of players in the game
    private $roundNumber;           // current round number
    private $turnNumberInRound;     // current turn number in current round
    private $activePlayerIdx;       // index of the active player in playerIdxArray
    private $playerWithInitiativeIdx; // index of the player who won initiative
    private $buttonArray;           // buttons for all players
    private $activeDieArrayArray;   // active dice for all players
    private $attack;                // array('attackerPlayerIdx',
                                    //       'defenderPlayerIdx',
                                    //       'attackerAttackDieIdxArray',
                                    //       'defenderAttackDieIdxArray',
                                    //       'attackType')
    private $attackerPlayerIdx;     // index in playerIdxArray of the attacker
    private $defenderPlayerIdx;     // index in playerIdxArray of the defender
    private $attackerAllDieArray;   // array of all attacker's dice
    private $defenderAllDieArray;   // array of all defender's dice
    private $attackerAttackDieArray; // array of attacker's dice used in attack
    private $defenderAttackDieArray; // array of defender's dice used in attack
    private $auxiliaryDieDecisionArrayArray; // array storing player decisions about auxiliary dice
    private $nRecentPasses;         // number of consecutive passes
    private $capturedDieArrayArray; // captured dice for all players
    private $roundScoreArray;       // current points score in this round
    private $gameScoreArrayArray;   // number of games W/L/D for all players
    private $isPrevRoundWinnerArray;// boolean array whether each player won the previous round
    private $maxWins;               // the game ends when a player has this many wins
    private $gameState;             // current game state as a BMGameState enum
    private $waitingOnActionArray;  // boolean array whether each player needs to perform an action
    private $autopassArray;         // boolean array whether each player has enabled autopass
    private $actionLog;             // game actions taken by this BMGame instance
    private $chat;                  // chat message submitted by the active player with an attack
    private $message;               // message to be passed to the GUI

    public $swingRequestArrayArray;
    public $swingValueArrayArray;

    public $allValuesSpecified = FALSE;

    public function require_values() {
        if (!$this->allValuesSpecified) {
            throw new Exception("require_values called");
        }
    }


    // methods
    public function do_next_step() {
        if (!isset($this->gameState)) {
            throw new LogicException('Game state must be set.');
        }

        $this->debug_message = 'ok';

        $this->run_die_hooks($this->gameState);

        switch ($this->gameState) {
            case BMGameState::START_GAME:
                // do_next_step is normally never run for BMGameState::START_GAME
                break;

            case BMGameState::APPLY_HANDICAPS:
                // ignore for the moment
                $this->gameScoreArrayArray =
                    array_fill(
                        0,
                        count($this->playerIdArray),
                        array('W' => 0, 'L' => 0, 'D' => 0)
                    );
                break;

            case BMGameState::CHOOSE_AUXILIARY_DICE:
                // james: this game state will probably move to after LOAD_DICE_INTO_BUTTONS
                $auxiliaryDice = '';
                // create list of auxiliary dice
                foreach ($this->buttonArray as $tempButton) {
                    if (BMGame::does_recipe_have_auxiliary_dice($tempButton->recipe)) {
                        $tempSplitArray = BMGame::separate_out_auxiliary_dice(
                            $tempButton->recipe
                        );
                        $auxiliaryDice = $auxiliaryDice.' '.$tempSplitArray[1];
                    }
                }
                $auxiliaryDice = trim($auxiliaryDice);
                // update $auxiliaryDice based on player choices
                $this->activate_GUI('ask_all_players_about_auxiliary_dice', $auxiliaryDice);

                //james: current default is to accept all auxiliary dice

                // update all button recipes and remove auxiliary markers
                if (!empty($auxiliaryDice)) {
                    foreach ($this->buttonArray as $buttonIdx => $tempButton) {
                        $separatedDice = BMGame::separate_out_auxiliary_dice($tempButton->recipe);
                        $tempButton->recipe = $separatedDice[0].' '.$auxiliaryDice;
                    }
                }
                break;

            case BMGameState::LOAD_DICE_INTO_BUTTONS:
            //james: this will be replaced with a call to the database
                // load clean version of the buttons from their recipes
                // if the player has not just won a round
//                foreach ($this->buttonArray as $playerIdx => $tempButton) {
//                    if (!$this->isPrevRoundWinnerArray[$playerIdx]) {
//                        $tempButton->reload();
//                    }
//                }
                break;

            case BMGameState::ADD_AVAILABLE_DICE_TO_GAME:
                // load BMGame activeDieArrayArray from BMButton dieArray
                $this->activeDieArrayArray =
                    array_fill(0, $this->nPlayers, array());

                foreach ($this->buttonArray as $buttonIdx => $tempButton) {
                    $tempButton->activate();
                }

                // load swing values that are carried across from a previous round
                if (!isset($this->swingValueArrayArray)) {
                    break;
                }

                foreach ($this->activeDieArrayArray as $playerIdx => &$activeDieArray) {
                    foreach ($activeDieArray as $dieIdx => &$activeDie) {
                        if ($activeDie instanceof BMDieSwing) {
                            if (array_key_exists(
                                $activeDie->swingType,
                                $this->swingValueArrayArray[$playerIdx]
                            )) {
                                $activeDie->swingValue =
                                    $this->swingValueArrayArray[$playerIdx][$activeDie->swingType];
                            }
                        }
                    }
                }
                break;

            case BMGameState::SPECIFY_DICE:
                $this->waitingOnActionArray =
                    array_fill(0, count($this->playerIdArray), FALSE);

                if (isset($this->swingRequestArrayArray)) {
                    foreach ($this->swingRequestArrayArray as $playerIdx => $swingRequestArray) {
                        $keyArray = array_keys($swingRequestArray);

                        // initialise swingValueArrayArray if necessary
                        if (!isset($this->swingValueArrayArray[$playerIdx])) {
                            $this->swingValueArrayArray[$playerIdx] = array();
                        }

                        foreach ($keyArray as $key) {
                            // copy swing request keys to swing value keys if they
                            // do not already exist
                            if (!array_key_exists($key, $this->swingValueArrayArray[$playerIdx])) {
                                $this->swingValueArrayArray[$playerIdx][$key] = NULL;
                            }

                            // set waitingOnActionArray based on if there are
                            // unspecified swing dice for that player
                            if (is_null($this->swingValueArrayArray[$playerIdx][$key])) {
                                $this->waitingOnActionArray[$playerIdx] = TRUE;
                            }
                        }
                    }

                    foreach ($this->waitingOnActionArray as $playerIdx => $waitingOnAction) {
                        if ($waitingOnAction) {
                            $this->activate_GUI('Waiting on player action.', $playerIdx);
                        } else {

                            // apply swing values
                            foreach ($this->activeDieArrayArray[$playerIdx] as $dieIdx => $die) {
                                if (isset($die->swingType)) {
                                    $isSetSuccessful = $die->set_swingValue(
                                        $this->swingValueArrayArray[$playerIdx]
                                    );
                                    // act appropriately if the swing values are invalid
                                    if (!$isSetSuccessful) {
                                        $this->message = 'Invalid value submitted for swing die ' . $die->recipe;
                                        $this->swingValueArrayArray[$playerIdx] = array();
                                        $this->waitingOnActionArray[$playerIdx] = TRUE;
                                        break 3;
                                    }
                                }
                            }
                        }
                    }
                }

                // roll dice
                foreach ($this->activeDieArrayArray as $playerIdx => $activeDieArray) {
                    foreach ($activeDieArray as $dieIdx => $die) {
                        if ($die instanceof BMDieSwing) {
                            if ($die->needsSwingValue) {
                                // swing value has not yet been set
                                continue;
                            }
                        }
                        $this->activeDieArrayArray[$playerIdx][$dieIdx] =
                            $die->make_play_die(FALSE);
                    }
                }
                break;

            case BMGameState::DETERMINE_INITIATIVE:
                $doesPlayerHaveInitiativeArray =
                  BMGame::does_player_have_initiative_array($this->activeDieArrayArray);

                if (array_sum($doesPlayerHaveInitiativeArray) > 1) {
                    $playersWithInitiative = array();
                    foreach ($doesPlayerHaveInitiativeArray as $playerIdx => $tempHasInitiative) {
                        if ($tempHasInitiative) {
                            $playersWithInitiative[] = $playerIdx;
                        }
                    }
                    $tempPlayerWithInitiativeIdx = array_rand($playersWithInitiative);
                } else {
                    $tempPlayerWithInitiativeIdx =
                        array_search(TRUE, $doesPlayerHaveInitiativeArray, TRUE);
                }

                $this->playerWithInitiativeIdx = $tempPlayerWithInitiativeIdx;
                break;

            case BMGameState::REACT_TO_INITIATIVE:
                $canReactArray = array_fill(0, $this->nPlayers, FALSE);

                foreach ($this->activeDieArrayArray as $playerIdx => $activeDieArray) {
                    // do nothing if a player has won initiative
                    if ($this->playerWithInitiativeIdx == $playerIdx) {
                        continue;
                    }

                    // find out if any of the dice have the ability to react
                    // when the player loses initiative
                    foreach ($activeDieArray as $activeDie) {
                        if ($activeDie->disabled) {
                            continue;
                        }
                        $hookResultArray =
                            $activeDie->run_hooks(
                                'react_to_initiative',
                                array('activeDieArrayArray' => $this->activeDieArrayArray,
                                      'playerIdx' => $playerIdx)
                            );
                        if (is_array($hookResultArray) && count($hookResultArray) > 0) {
                            foreach ($hookResultArray as $hookResult) {
                                if (TRUE === $hookResult) {
                                    $canReactArray[$playerIdx] = TRUE;
                                    continue;
                                }
                            }
                        }
                    }
                }

                $this->waitingOnActionArray = $canReactArray;

                break;

            case BMGameState::START_ROUND:
                if (!isset($this->playerWithInitiativeIdx)) {
                    throw new LogicException(
                        'Player that has won initiative must already have been determined.'
                    );
                }
                // set BMGame activePlayerIdx
                $this->activePlayerIdx = $this->playerWithInitiativeIdx;
                $this->turnNumberInRound = 1;
                break;

            case BMGameState::START_TURN:
                // deal with autopass
                if (!isset($this->attack) &&
                    $this->autopassArray[$this->activePlayerIdx] &&
                    $this->turnNumberInRound > 1) {
                    $validAttackTypes = $this->valid_attack_types();
                    if (array_search('Pass', $validAttackTypes) &&
                        (1 == count($validAttackTypes))) {
                        $this->attack = array('attackerPlayerIdx' => $this->activePlayerIdx,
                                              'defenderPlayerIdx' => NULL,
                                              'attackerAttackDieIdxArray' => array(),
                                              'defenderAttackDieIdxArray' => array(),
                                              'attackType' => 'Pass');
                    }
                }

                // display dice
                $this->activate_GUI('show_active_dice');

                // while attack has not been set {ask player to select attack}
                while (!isset($this->attack)) {
                    $this->activate_GUI('wait_for_attack');
                    $this->waitingOnActionArray[$this->activePlayerIdx] = TRUE;
                    return;
                }

                // validate attacker player idx
                if ($this->activePlayerIdx !== $this->attack['attackerPlayerIdx']) {
                    $this->message = 'Attacker must be current active player.';
                    $this->attack = NULL;
                    return;
                }

                // validate defender player idx
                if ($this->attack['attackerPlayerIdx'] === $this->attack['defenderPlayerIdx']) {
                    $this->message = 'Attacker must be different to defender.';
                    $this->attack = NULL;
                    return;
                }

                // perform attack
                $attack = BMAttack::get_instance($this->attack['attackType']);

                $this->attackerPlayerIdx = $this->attack['attackerPlayerIdx'];
                $this->defenderPlayerIdx = $this->attack['defenderPlayerIdx'];
                $attackerAttackDieArray = array();
                foreach ($this->attack['attackerAttackDieIdxArray'] as $attackerAttackDieIdx) {
                    $attackDie =
                        $this->activeDieArrayArray[$this->attack['attackerPlayerIdx']]
                                                  [$attackerAttackDieIdx];
                    if ($attackDie->disabled) {
                        $this->message = 'Attempting to attack with a disabled die.';
                        $this->attack = NULL;
                        return;
                    }
                    $attackerAttackDieArray[] = $attackDie;
                }
                $defenderAttackDieArray = array();
                foreach ($this->attack['defenderAttackDieIdxArray'] as $defenderAttackDieIdx) {
                    $defenderAttackDieArray[] =
                        &$this->activeDieArrayArray[$this->attack['defenderPlayerIdx']]
                                                   [$defenderAttackDieIdx];
                }

                foreach ($attackerAttackDieArray as $attackDie) {
                    $attack->add_die($attackDie);
                }

                $valid = $attack->validate_attack(
                    $this,
                    $attackerAttackDieArray,
                    $defenderAttackDieArray
                );

                if (!$valid) {
                    $this->activate_GUI('Invalid attack');
                    $this->waitingOnActionArray[$this->activePlayerIdx] = TRUE;
                    $this->attack = NULL;
                    return;
                }

                $preAttackDice = $this->get_action_log_data(
                    $attackerAttackDieArray,
                    $defenderAttackDieArray
                );

                $this->turnNumberInRound++;
                $attack->commit_attack($this, $attackerAttackDieArray, $defenderAttackDieArray);

                $postAttackDice = $this->get_action_log_data(
                    $attackerAttackDieArray,
                    $defenderAttackDieArray
                );
                $this->log_attack($preAttackDice, $postAttackDice);

                if (isset($this->activePlayerIdx)) {
                    $this->update_active_player();
                }

                break;

            case BMGameState::END_TURN:
                break;

            case BMGameState::END_ROUND:
                $roundScoreArray = $this->get_roundScoreArray();

                // check for draw currently assumes only two players
                $isDraw = $roundScoreArray[0] == $roundScoreArray[1];

                if ($isDraw) {
                    for ($playerIdx = 0; $playerIdx < $this->nPlayers; $playerIdx++) {
                        $this->gameScoreArrayArray[$playerIdx]['D']++;
                        // james: currently there is no code for three draws in a row
                    }
                    $this->log_action(
                        'end_draw',
                        0,
                        'Round ' . ($this->get_roundNumber() - 1) . ' ended in a draw (' .
                        $roundScoreArray[0] . ' vs. ' . $roundScoreArray[1] . ')'
                    );
                } else {
                    $winnerIdx = array_search(max($roundScoreArray), $roundScoreArray);

                    for ($playerIdx = 0; $playerIdx < $this->nPlayers; $playerIdx++) {
                        if ($playerIdx == $winnerIdx) {
                            $this->gameScoreArrayArray[$playerIdx]['W']++;
                        } else {
                            $this->gameScoreArrayArray[$playerIdx]['L']++;
                            $this->swingValueArrayArray[$playerIdx] = array();
                        }
                    }
                    $this->log_action(
                        'end_winner',
                        $this->playerIdArray[$winnerIdx],
                        'won round ' . ($this->get_roundNumber() - 1) . ' (' .
                        $roundScoreArray[0] . ' vs ' . $roundScoreArray[1] . ')'
                    );
                }
                $this->reset_play_state();
                break;

            case BMGameState::END_GAME:
                $this->reset_play_state();
                // swingValueArrayArray must be reset to clear entries in the
                // database table game_swing_map
                $this->swingValueArrayArray = array_fill(0, $this->nPlayers, array());

                $this->activate_GUI('Show end-of-game screen.');
                break;
        }
    }

    public function update_game_state() {
        if (!isset($this->gameState)) {
            throw new LogicException('Game state must be set.');
        }

        switch ($this->gameState) {
            case BMGameState::START_GAME:
                $this->reset_play_state();

                // if buttons are unspecified, allow players to choose buttons
                for ($playerIdx = 0, $nPlayers = count($this->playerIdArray);
                     $playerIdx <= $nPlayers - 1;
                     $playerIdx++) {
                    if (!isset($this->buttonArray[$playerIdx])) {
                        $this->waitingOnActionArray[$playerIdx] = TRUE;
                        $this->activate_GUI('Prompt for button ID', $playerIdx);
                    }
                }

                // require both players and buttons to be specified
                $allButtonsSet = count($this->playerIdArray) === count($this->buttonArray);

                if (!in_array(0, $this->playerIdArray) &&
                    $allButtonsSet) {
                    $this->gameState = BMGameState::APPLY_HANDICAPS;
                    $this->nRecentPasses = 0;
                    $this->autopassArray = array_fill(0, $this->nPlayers, FALSE);
                    $this->gameScoreArrayArray = array_fill(0, $this->nPlayers, array(0, 0, 0));
                }

                break;

            case BMGameState::APPLY_HANDICAPS:
                if (!isset($this->maxWins)) {
                    throw new LogicException(
                        'maxWins must be set before applying handicaps.'
                    );
                };
                if (isset($this->gameScoreArrayArray)) {
                    $nWins = 0;
                    foreach ($this->gameScoreArrayArray as $tempGameScoreArray) {
                        if ($nWins < $tempGameScoreArray['W']) {
                            $nWins = $tempGameScoreArray['W'];
                        }
                    }
                    if ($nWins >= $this->maxWins) {
                        $this->gameState = BMGameState::END_GAME;
                    } else {
                        $this->gameState = BMGameState::CHOOSE_AUXILIARY_DICE;
                    }
                }
                break;

            case BMGameState::CHOOSE_AUXILIARY_DICE:
                $containsAuxiliaryDice = FALSE;
                foreach ($this->buttonArray as $tempButton) {
                    if ($this->does_recipe_have_auxiliary_dice($tempButton->recipe)) {
                        $containsAuxiliaryDice = TRUE;
                        break;
                    }
                }
                if (!$containsAuxiliaryDice) {
                    $this->gameState = BMGameState::LOAD_DICE_INTO_BUTTONS;
                }
                break;

            case BMGameState::LOAD_DICE_INTO_BUTTONS:
                if (!isset($this->buttonArray)) {
                    throw new LogicException(
                        'Button array must be set before loading dice into buttons.'
                    );
                }

                $buttonsLoadedWithDice = TRUE;
                foreach ($this->buttonArray as $tempButton) {
                    if (!isset($tempButton->dieArray)) {
                        $buttonsLoadedWithDice = FALSE;
                        break;
                    }
                }
                if ($buttonsLoadedWithDice) {
                    $this->gameState = BMGameState::ADD_AVAILABLE_DICE_TO_GAME;
                }
                break;

            case BMGameState::ADD_AVAILABLE_DICE_TO_GAME:
                if (isset($this->activeDieArrayArray)) {
                    $this->gameState = BMGameState::SPECIFY_DICE;
                }
                break;

            case BMGameState::SPECIFY_DICE:
                $areAllDiceSpecified = TRUE;
                foreach ($this->activeDieArrayArray as $activeDieArray) {
                    foreach ($activeDieArray as $tempDie) {
                        if (!$this->is_die_specified($tempDie)) {
                            $areAllDiceSpecified = FALSE;
                            break 2;
                        }
                    }
                }
                if ($areAllDiceSpecified) {
                    $this->gameState = BMGameState::DETERMINE_INITIATIVE;
                }
                break;

            case BMGameState::DETERMINE_INITIATIVE:
                if (isset($this->playerWithInitiativeIdx)) {
                    $this->gameState = BMGameState::REACT_TO_INITIATIVE;
                }
                break;

            case BMGameState::REACT_TO_INITIATIVE:
                // if everyone is out of actions, reactivate chance dice
                if (0 == array_sum($this->waitingOnActionArray)) {
                    $this->gameState = BMGameState::START_ROUND;
                    if (isset($this->activeDieArrayArray)) {
                        foreach ($this->activeDieArrayArray as &$activeDieArray) {
                            if (isset($activeDieArray)) {
                                foreach ($activeDieArray as &$activeDie) {
                                    if ($activeDie->has_skill('Chance')) {
                                        unset($activeDie->disabled);
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            case BMGameState::START_ROUND:
                if (isset($this->activePlayerIdx)) {
                    $this->gameState = BMGameState::START_TURN;
                }
                break;

            case BMGameState::START_TURN:
                if ((isset($this->attack)) &&
                    FALSE === array_search(TRUE, $this->waitingOnActionArray, TRUE)) {
                    $this->gameState = BMGameState::END_TURN;
                    if (isset($this->activeDieArrayArray) &&
                        isset($this->attack['attackerPlayerIdx'])) {
                        foreach ($this->activeDieArrayArray[$this->attack['attackerPlayerIdx']] as &$activeDie) {
                            if ($activeDie->disabled) {
                                if ($activeDie->has_skill('Focus')) {
                                    unset($activeDie->disabled);
                                }
                            }
                        }
                    }
                }
                break;

            case BMGameState::END_TURN:
                $nDice = array_map("count", $this->activeDieArrayArray);
                // check if any player has no dice, or if everyone has passed
                if ((0 === min($nDice)) ||
                    ($this->nPlayers == $this->nRecentPasses)) {
                    $this->gameState = BMGameState::END_ROUND;
                } else {
                    $this->gameState = BMGameState::START_TURN;
                    $this->waitingOnActionArray[$this->activePlayerIdx] = TRUE;
                }
                $this->attack = NULL;
                break;

            case BMGameState::END_ROUND:
                if (isset($this->activePlayerIdx)) {
                    break;
                }
                // james: still need to deal with reserve dice
                $this->gameState = BMGameState::LOAD_DICE_INTO_BUTTONS;
                foreach ($this->gameScoreArrayArray as $tempGameScoreArray) {
                    if ($tempGameScoreArray['W'] >= $this->maxWins) {
                        $this->gameState = BMGameState::END_GAME;
                        break;
                    }
                }
                break;

            case BMGameState::END_GAME:
                break;
        }
    }

    // The variable $gameStateBreakpoint is used for debugging purposes only.
    // If used, the game will stop as soon as the game state becomes

    public function proceed_to_next_user_action($gameStateBreakpoint = NULL) {
        $repeatCount = 0;
        $initialGameState = $this->gameState;
        $this->update_game_state();

        if (isset($gameStateBreakpoint) &&
            ($gameStateBreakpoint == $this->gameState) &&
            ($initialGameState != $this->gameState)) {
            return;
        }

        $this->do_next_step();

        while (0 === array_sum($this->waitingOnActionArray)) {
            $intermediateGameState = $this->gameState;
            $this->update_game_state();

            if (isset($gameStateBreakpoint) &&
                ($gameStateBreakpoint == $this->gameState)) {
                return;
            }

            $this->do_next_step();

            if (BMGameState::END_GAME === $this->gameState) {
                return;
            }

            if ($intermediateGameState === $this->gameState) {
                $repeatCount++;
            } else {
                $repeatCount = 0;
            }
            if ($repeatCount >= 20) {
                throw new LogicException(
                    'Infinite loop detected when advancing game state.'
                );
            }
        }
    }

    // react_to_initiative expects one of the following three input arrays:
    //
    //   1.  array('action' => 'chance',
    //             'playerIdx => $playerIdx,
    //             'rerolledDieIdx' => $rerolledDieIdx)
    //       where the index of the rerolled chance die is in $rerolledDieIdx
    //
    //   2.  array('action' => 'decline',
    //             'playerIdx' => $playerIdx)
    //
    //   3.  array('action' => 'focus',
    //             'playerIdx' => $playerIdx,
    //             'focusValueArray' => array($dieIdx1 => $dieValue1,
    //                                        $dieIdx2 => $dieValue2))
    //       where the details of ALL focus dice are in $focusValueArray
    //
    // It returns a boolean telling whether the reaction has been successful.
    // If it fails, $game->message will say why it has failed.

    // $gainedInitiativeOverride is used for testing purposes only

    public function react_to_initiative(array $args, $gainedInitiativeOverride = NULL) {
        if (BMGameState::REACT_TO_INITIATIVE != $this->gameState) {
            $this->message = 'Wrong game state to react to initiative.';
            return FALSE;
        }

        if (!array_key_exists('action', $args) ||
            !array_key_exists('playerIdx', $args)) {
            $this->message = 'Missing action or player index.';
            return FALSE;
        }

        $playerIdx = $args['playerIdx'];
        $waitingOnActionArray = &$this->waitingOnActionArray;
        $waitingOnActionArray[$playerIdx] = FALSE;


        switch ($args['action']) {
            case 'chance':
                if (!array_key_exists('rerolledDieIdx', $args)) {
                    $this->message = 'rerolledDieIdx must exist.';
                    return FALSE;
                }

                if (FALSE ===
                    filter_var(
                        $args['rerolledDieIdx'],
                        FILTER_VALIDATE_INT,
                        array("options"=>
                              array("min_range"=>0,
                                    "max_range"=>count($this->activeDieArrayArray[$playerIdx]) - 1))
                    )) {
                    $this->message = 'Invalid die index.';
                    return FALSE;
                }

                $die = $this->activeDieArrayArray[$playerIdx][$args['rerolledDieIdx']];
                if (FALSE === array_search('BMSkillChance', $die->skillList)) {
                    $this->message = 'Can only apply chance action to chance die.';
                    return FALSE;
                }

                $die->roll();
                foreach ($this->activeDieArrayArray[$playerIdx] as &$die) {
                    if ($die->has_skill('Chance')) {
                        $die->disabled = TRUE;
                    }
                }

                $newInitiativeArray = BMGame::does_player_have_initiative_array(
                    $this->activeDieArrayArray
                );
                $gainedInitiative = $newInitiativeArray[$playerIdx] &&
                                    (1 == array_sum($newInitiativeArray));
                $this->gameState = BMGameState::DETERMINE_INITIATIVE;
                break;
            case 'decline':
                $gainedInitiative = FALSE;
                if (0 == array_sum($this->waitingOnActionArray)) {
                    $this->gameState = BMGameState::START_ROUND;
                }
                break;
            case 'focus':
                if (!array_key_exists('focusValueArray', $args)) {
                    $this->message = 'focusValueArray must exist.';
                    return FALSE;
                }

                // check new die values
                $focusValueArray = $args['focusValueArray'];

                if (!is_array($focusValueArray) || (0 == count($focusValueArray))) {
                    $this->message = 'focusValueArray must be a non-empty array.';
                    return FALSE;
                }

                // focusValueArray should have the form array($dieIdx1 => $dieValue1, ...)
                foreach ($focusValueArray as $dieIdx => $newDieValue) {
                    if (FALSE ===
                        filter_var(
                            $dieIdx,
                            FILTER_VALIDATE_INT,
                            array("options"=>
                                  array("min_range"=>0,
                                        "max_range"=>count($this->activeDieArrayArray[$playerIdx]) - 1))
                        )) {
                        $this->message = 'Invalid die index.';
                        return FALSE;
                    }

                    $die = $this->activeDieArrayArray[$playerIdx][$dieIdx];

                    if (FALSE ===
                        filter_var(
                            $newDieValue,
                            FILTER_VALIDATE_INT,
                            array("options"=>
                                  array("min_range"=>$die->min,
                                        "max_range"=>$die->value))
                        )) {
                        $this->message = 'Invalid value for focus die.';
                        return FALSE;
                    }

                    if (FALSE === array_search('BMSkillFocus', $die->skillList)) {
                        $this->message = 'Can only apply focus action to focus die.';
                        return FALSE;
                    }
                }

                // change specified die values
                $oldDieValueArray = array();
                foreach ($focusValueArray as $dieIdx => $newDieValue) {
                    $oldDieValueArray[$dieIdx] = $this->activeDieArrayArray[$playerIdx][$dieIdx]->value;
                    $this->activeDieArrayArray[$playerIdx][$dieIdx]->value = $newDieValue;
                }
                $newInitiativeArray = BMGame::does_player_have_initiative_array(
                    $this->activeDieArrayArray
                );

                // if the change is successful, disable focus dice that changed
                // value
                if ($newInitiativeArray[$playerIdx] &&
                    1 == array_sum($newInitiativeArray)) {
                    foreach ($oldDieValueArray as $dieIdx => $oldDieValue) {
                        if ($oldDieValue >
                            $this->activeDieArrayArray[$playerIdx][$dieIdx]->value) {
                            $this->activeDieArrayArray[$playerIdx][$dieIdx]->disabled = TRUE;
                        }
                    }

                    // re-enable opponents' disabled focus dice
                    foreach ($this->activeDieArrayArray as $tempPlayerIdx => &$activeDieArray) {
                        if ($playerIdx == $tempPlayerIdx) {
                            continue;
                        }

                        foreach ($activeDieArray as &$die) {
                            if ($die->has_skill('Focus') && isset($die->disabled)) {
                                unset($die->disabled);
                            }
                        }
                    }
                } else {
                    // if the change does not gain initiative unambiguously, it is
                    // invalid, so reset die values to original values
                    foreach ($oldDieValueArray as $dieIdx => $oldDieValue) {
                        $this->activeDieArrayArray[$playerIdx][$dieIdx]->value = $oldDieValue;
                    }
                    $this->message = 'Focus dice not set low enough.';
                    return FALSE;
                }
                $this->gameState = BMGameState::DETERMINE_INITIATIVE;
                $gainedInitiative = TRUE;
                break;
            default:
                $this->message = 'Invalid reaction to initiative.';
                return FALSE;
        }

        if (isset($gainedInitiativeOverride)) {
            $gainedInitiative = $gainedInitiativeOverride;
        }

        if ($gainedInitiative) {
            // re-enable all disabled chance dice for other players
            foreach ($this->activeDieArrayArray as $pIdx => &$activeDieArray) {
                if ($playerIdx == $pIdx) {
                    continue;
                }
                foreach ($activeDieArray as &$activeDie) {
                    if ($activeDie->has_skill('Chance')) {
                        unset($activeDie->disabled);
                    }
                }
            }
        }

        $this->do_next_step();

        return array('gained_initiative' => $gainedInitiative);
    }

    protected function run_die_hooks($gameState, array $args = array()) {
        $args['activePlayerIdx'] = $this->activePlayerIdx;

        if (!empty($this->activeDieArrayArray)) {
            foreach ($this->activeDieArrayArray as $activeDieArray) {
                foreach ($activeDieArray as $activeDie) {
                    $activeDie->run_hooks_at_game_state($gameState, $args);
                }
            }
        }

        if (!empty($this->capturedDieArrayArray)) {
            foreach ($this->capturedDieArrayArray as $capturedDieArray) {
                foreach ($capturedDieArray as $capturedDie) {
                    $capturedDie->run_hooks_at_game_state($gameState, $args);
                }
            }
        }
    }

    public function add_die($die) {
        if (!isset($this->activeDieArrayArray)) {
            throw new LogicException(
                'activeDieArrayArray must be set before a die can be added.'
            );
        }

        $this->activeDieArrayArray[$die->playerIdx][] = $die;
    }

    public function capture_die($die, $newOwnerIdx = NULL) {
        if (!isset($this->activeDieArrayArray)) {
            throw new LogicException(
                'activeDieArrayArray must be set before capturing dice.'
            );
        }

        foreach ($this->activeDieArrayArray as $playerIdx => $activeDieArray) {
            $dieIdx = array_search($die, $activeDieArray, TRUE);
            if (FALSE !== $dieIdx) {
                break;
            }
        }

        if (FALSE === $dieIdx) {
            throw new LogicException('Captured die does not exist.');
        }

        // add captured die to captured die array
        if (is_null($newOwnerIdx)) {
            $newOwnerIdx = $this->attack['attackerPlayerIdx'];
        }
        $this->capturedDieArrayArray[$newOwnerIdx][] =
            $this->activeDieArrayArray[$playerIdx][$dieIdx];
        // remove captured die from active die array
        array_splice($this->activeDieArrayArray[$playerIdx], $dieIdx, 1);
    }

    public function request_swing_values($die, $swingtype, $playerIdx) {
        if (!isset($this->swingRequestArrayArray)) {
            $this->swingRequestArrayArray =
                array_fill(0, $this->nPlayers, array());
        }
        $this->swingRequestArrayArray[$playerIdx][$swingtype][] = $die;
    }

    public static function does_recipe_have_auxiliary_dice($recipe) {
        if (FALSE === strpos($recipe, '+')) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public static function separate_out_auxiliary_dice($recipe) {
        $dieRecipeArray = explode(' ', $recipe);

        $nonAuxiliaryDice = '';
        $auxiliaryDice = '';

        foreach ($dieRecipeArray as $tempDieRecipe) {
            if (FALSE === strpos($tempDieRecipe, '+')) {
                $nonAuxiliaryDice = $nonAuxiliaryDice.$tempDieRecipe.' ';
            } else {
                $strippedDieRecipe = str_replace('+', '', $tempDieRecipe);
                $auxiliaryDice = $auxiliaryDice.$strippedDieRecipe.' ';
            }
        }

        $nonAuxiliaryDice = trim($nonAuxiliaryDice);
        $auxiliaryDice = trim($auxiliaryDice);

        return array($nonAuxiliaryDice, $auxiliaryDice);
    }

    public static function does_player_have_initiative_array(array $activeDieArrayArray) {
        $initiativeArrayArray = array();
        foreach ($activeDieArrayArray as $playerIdx => $tempActiveDieArray) {
            $initiativeArrayArray[] = array();
            foreach ($tempActiveDieArray as $dieIdx => $tempDie) {
                // update initiative arrays if die counts for initiative
                $tempInitiative = $tempDie->initiative_value();
                if ($tempInitiative > 0) {
                    $initiativeArrayArray[$playerIdx][] = $tempInitiative;
                }
            }
            sort($initiativeArrayArray[$playerIdx]);
        }

        // determine player that has won initiative
        $nPlayers = count($activeDieArrayArray);
        $doesPlayerHaveInitiative = array_fill(0, $nPlayers, TRUE);

        $dieIdx = 0;
        while (array_sum($doesPlayerHaveInitiative) >= 2) {
            $dieValues = array();
            foreach ($initiativeArrayArray as $tempInitiativeArray) {
                if (isset($tempInitiativeArray[$dieIdx])) {
                    $dieValues[] = $tempInitiativeArray[$dieIdx];
                } else {
                    $dieValues[] = PHP_INT_MAX;
                }
            }
            $minDieValue = min($dieValues);
            if (PHP_INT_MAX === $minDieValue) {
                break;
            }
            for ($playerIdx = 0; $playerIdx <= $nPlayers - 1; $playerIdx++) {
                if ($dieValues[$playerIdx] > $minDieValue) {
                    $doesPlayerHaveInitiative[$playerIdx] = FALSE;
                }
            }
            $dieIdx++;
        }

        return $doesPlayerHaveInitiative;
    }

    // james: parts of this function needs to be moved to the BMDie class
    public static function is_die_specified($die) {
        // A die can be unspecified if it is swing, option, or plasma.

        // If swing or option, then it is unspecified if the sides are unclear.
        // check for swing letter or option '/' inside the brackets
        // remove everything before the opening parenthesis

//        $sides = $die->max;

//        if (strlen(preg_replace('#[^[:alpha:]/]#', '', $sides)) > 0) {
//            return FALSE;
//        }

        // If plasma, then it is unspecified if the skills are unclear.
        // james: not written yet

        return (isset($die->max));
    }

    public function valid_attack_types() {
        // james: assume two players at the moment
        $attackerIdx = $this->activePlayerIdx;
        $defenderIdx = ($attackerIdx + 1) % 2;

        $attackTypeArray = BMAttack::possible_attack_types($this->activeDieArrayArray[$attackerIdx]);

        $validAttackTypeArray = array();

        // find out if there are any possible attacks with any combination of
        // the attacker's and defender's dice
        foreach ($attackTypeArray as $idx => $attackType) {
            $this->attack = array('attackerPlayerIdx' => $attackerIdx,
                                  'defenderPlayerIdx' => $defenderIdx,
                                  'attackerAttackDieIdxArray' =>
                                      range(0, count($this->activeDieArrayArray[$attackerIdx]) - 1),
                                  'defenderAttackDieIdxArray' =>
                                      range(0, count($this->activeDieArrayArray[$defenderIdx]) - 1),
                                  'attackType' => $attackTypeArray[$idx]);
            $attack = BMAttack::get_instance($attackType);
            foreach ($this->activeDieArrayArray[$attackerIdx] as $attackDie) {
                $attack->add_die($attackDie);
            }
            if ($attack->find_attack($this)) {
                $validAttackTypeArray[$attackType] = $attackType;
            }
        }

        if (empty($validAttackTypeArray)) {
            $validAttackTypeArray['Pass'] = 'Pass';
        }

        // james: deliberately ignore Surrender attacks here, so that it
        //        does not appear in the list of attack types

        return $validAttackTypeArray;
    }

    private function activate_GUI($activation_type, $input_parameters = NULL) {
        // currently acts as a placeholder
        $this->debug_message = $this->debug_message.'\n'.
                         $activation_type.' '.$input_parameters;
    }

    public function reset_play_state() {
        $this->activePlayerIdx = NULL;
        $this->playerWithInitiativeIdx = NULL;
        $this->activeDieArrayArray = NULL;
        $this->attack = NULL;

        $nPlayers = count($this->playerIdArray);
        $this->nRecentPasses = 0;
        $this->turnNumberInRound = 0;
        $this->capturedDieArrayArray = array_fill(0, $nPlayers, array());
        $this->waitingOnActionArray = array_fill(0, $nPlayers, FALSE);
    }

    private function update_active_player() {
        if (!isset($this->activePlayerIdx)) {
            throw new LogicException(
                'Active player must be set before it can be updated.'
            );
        }

        $nPlayers = count($this->playerIdArray);
        // move to the next player
        $this->activePlayerIdx = ($this->activePlayerIdx + 1) % $nPlayers;

        // currently not waiting on anyone
        $this->waitingOnActionArray = array_fill(0, $nPlayers, FALSE);
    }

    // utility methods
    public function __construct(
        $gameID = 0,
        array $playerIdArray = array(0, 0),
        array $buttonRecipeArray = array('', ''),
        $maxWins = 3
    ) {
        if (count($playerIdArray) !== count($buttonRecipeArray)) {
            throw new InvalidArgumentException(
                'Number of buttons must equal the number of players.'
            );
        }

        $nPlayers = count($playerIdArray);
        $this->nPlayers = $nPlayers;
        $this->gameId = $gameID;
        $this->playerIdArray = $playerIdArray;
        $this->gameState = BMGameState::START_GAME;
        $this->waitingOnActionArray = array_fill(0, $nPlayers, FALSE);
        foreach ($buttonRecipeArray as $buttonIdx => $tempRecipe) {
            if (strlen($tempRecipe) > 0) {
                $tempButton = new BMButton;
                $tempButton->load($tempRecipe);
                $this->buttonArray[$buttonIdx] = $tempButton;
            }
        }
        $this->maxWins = $maxWins;
        $this->isPrevRoundWinnerArray = array_fill(0, $nPlayers, FALSE);
        $this->actionLog = array();
    }

    private function get_roundNumber() {
        return(
            min(
                $this->maxWins,
                array_sum($this->gameScoreArrayArray[0]) + 1
            )
        );
    }

    private function get_roundScoreArray() {
        $roundScoreTimesTenArray = array_fill(0, $this->nPlayers, 0);
        $roundScoreArray = array_fill(0, $this->nPlayers, 0);

        foreach ((array)$this->activeDieArrayArray as $playerIdx => $activeDieArray) {
            $activeDieScoreTimesTen = 0;
            foreach ($activeDieArray as $activeDie) {
                $activeDieScoreTimesTen += $activeDie->get_scoreValueTimesTen();
            }
            $roundScoreTimesTenArray[$playerIdx] = $activeDieScoreTimesTen;
        }

        foreach ((array)$this->capturedDieArrayArray as $playerIdx => $capturedDieArray) {
            $capturedDieScoreTimesTen = 0;
            foreach ($capturedDieArray as $capturedDie) {
                $capturedDieScoreTimesTen += $capturedDie->get_scoreValueTimesTen();
            }
            $roundScoreTimesTenArray[$playerIdx] += $capturedDieScoreTimesTen;
        }

        foreach ($roundScoreTimesTenArray as $playerIdx => $roundScoreTimesTen) {
            $roundScoreArray[$playerIdx] = $roundScoreTimesTen/10;
        }

        return $roundScoreArray;
    }

    // record a game action in the history log
    private function log_action($actionType, $actingPlayerIdx, $message) {
        $this->actionLog[] = array(
            'gameState'  => $this->gameState,
            'actionType' => $actionType,
            'actingPlayerIdx' => $actingPlayerIdx,
            'message'    => $message,
        );
    }

    // empty the action log after its entries have been stored in
    // the database
    public function empty_action_log() {
        $this->actionLog = array();
    }

    // N.B. The chat text has not been sanitized at this point, so don't use it for anything
    public function add_chat($playerIdx, $chat) {
        $this->chat = array('playerIdx' => $playerIdx, 'chat' => $chat);
    }

    // special recording function for logging what changed as the result of an attack
    private function log_attack($preAttackDice, $postAttackDice) {
        $attackType = $this->attack['attackType'];

        // First, what type of attack was this?
        if ($attackType == 'Pass') {
            $this->message = 'passed';
        } else {
            $this->message = 'performed ' . $attackType . ' attack';

            // Add the pre-attack status of all participating dice
            $preAttackAttackers = array();
            $preAttackDefenders = array();
//            $attackerOutcomes = array();
//            $defenderOutcomes = array();
            foreach ($preAttackDice['attacker'] as $idx => $attackerInfo) {
                $preAttackAttackers[] = $attackerInfo['recipeStatus'];
            }
            foreach ($preAttackDice['defender'] as $idx => $defenderInfo) {
                $preAttackDefenders[] = $defenderInfo['recipeStatus'];
            }
            if (count($preAttackAttackers) > 0) {
                $this->message .= ' using [' . implode(",", $preAttackAttackers) . ']';
            }
            if (count($preAttackDefenders) > 0) {
                $this->message .= ' against [' . implode(",", $preAttackDefenders) . ']';
            }

            // Report what happened to each defending die
            foreach ($preAttackDice['defender'] as $idx => $defenderInfo) {
                $postInfo = $postAttackDice['defender'][$idx];
                $postEvents = array();
                if ($postInfo['captured']) {
                    $postEvents[] = 'was captured';
                } else {
                    $postEvents[] = 'was not captured';
                    if ($defenderInfo['doesReroll']) {
                        $postEvents[] = 'rerolled ' . $defenderInfo['value'] . ' => ' . $postInfo['value'];
                    } else {
                        $postEvents[] = 'does not reroll';
                    }
                }
                if ($defenderInfo['recipe'] != $postInfo['recipe']) {
                    $postEvents[] = 'recipe changed from ' . $defenderInfo['recipe'] . ' to ' . $postInfo['recipe'];
                }
                $this->message .= '; Defender ' . $defenderInfo['recipe'] . ' ' . implode(', ', $postEvents);
            }

            // Report what happened to each attacking die
            foreach ($preAttackDice['attacker'] as $idx => $attackerInfo) {
                $postInfo = $postAttackDice['attacker'][$idx];
                $postEvents = array();
                if ($attackerInfo['doesReroll']) {
                    $postEvents[] = 'rerolled ' . $attackerInfo['value'] . ' => ' . $postInfo['value'];
                } else {
                    $postEvents[] = 'does not reroll';
                }
                if ($attackerInfo['recipe'] != $postInfo['recipe']) {
                    $postEvents[] = 'recipe changed from ' . $attackerInfo['recipe'] . ' to ' . $postInfo['recipe'];
                }
                if (count($postEvents) > 0) {
                    $this->message .= '; Attacker ' . $attackerInfo['recipe'] . ' ' . implode(', ', $postEvents);
                }
            }
        }
        $this->log_action('attack', $this->playerIdArray[$this->attackerPlayerIdx], $this->message);
    }

    // get log-relevant data about the dice involved in an attack
    private function get_action_log_data($attackerDice, $defenderDice) {
        $attackData = array(
            'attacker' => array(),
            'defender' => array(),
        );
        foreach ($attackerDice as $attackerIdx => $attackerDie) {
            $attackData['attacker'][] = $attackerDie->get_action_log_data();
        }
        foreach ($defenderDice as $attackerIdx => $attackerDie) {
            $attackData['defender'][] = $attackerDie->get_action_log_data();
        }
        return $attackData;
    }

    // to allow array elements to be set directly, change the __get to &__get
    // to return the result by reference
    public function __get($property) {
        if (property_exists($this, $property)) {
            switch ($property) {
                case 'attackerPlayerIdx':
                    if (!isset($this->attack)) {
                        return NULL;
                    }
                    return $this->attack['attackerPlayerIdx'];
                case 'defenderPlayerIdx':
                    if (!isset($this->attack)) {
                        return NULL;
                    }
                    return $this->attack['defenderPlayerIdx'];
                case 'attackerAllDieArray':
                    if (!isset($this->attack) ||
                        !isset($this->activeDieArrayArray)) {
                        return NULL;
                    }
                    return $this->activeDieArrayArray[$this->attack['attackerPlayerIdx']];
                case 'defenderAllDieArray':
                    if (!isset($this->attack) ||
                        !isset($this->activeDieArrayArray)) {
                        return NULL;
                    }
                    return $this->activeDieArrayArray[$this->attack['defenderPlayerIdx']];
                case 'attackerAttackDieArray':
                    if (!isset($this->attack) ||
                        !isset($this->activeDieArrayArray)) {
                        return NULL;
                    }
                    $attackerAttackDieArray = array();
                    foreach ($this->attack['attackerAttackDieIdxArray'] as $attackerAttackDieIdx) {
                        $attackerAttackDieArray[] =
                            $this->activeDieArrayArray[$this->attack['attackerPlayerIdx']]
                                                      [$attackerAttackDieIdx];
                    }
                    return $attackerAttackDieArray;
                case 'defenderAttackDieArray':
                    if (!isset($this->attack)) {
                        return NULL;
                    }
                    $defenderAttackDieArray = array();
                    foreach ($this->attack['defenderAttackDieIdxArray'] as $defenderAttackDieIdx) {
                        $defenderAttackDieArray[] =
                            $this->activeDieArrayArray[$this->attack['defenderPlayerIdx']]
                                                      [$defenderAttackDieIdx];
                    }
                    return $defenderAttackDieArray;
                case 'roundNumber':
                    return $this->get_roundNumber();
                case 'roundScoreArray':
                    return $this->get_roundScoreArray();
                default:
                    return $this->$property;
            }
        }
    }

    public function __set($property, $value) {
        switch ($property) {
            case 'nPlayers':
                throw new LogicException(
                    'nPlayers is derived from BMGame->playerIdArray'
                );
            case 'turnNumberInRound':
                if (FALSE ===
                    filter_var(
                        $value,
                        FILTER_VALIDATE_INT,
                        array("options"=> array("min_range"=>0))
                    )) {
                    throw new InvalidArgumentException(
                        'Invalid turn number.'
                    );
                }
                $this->turnNumberInRound = $value;
                break;
            case 'gameId':
                if (FALSE ===
                    filter_var(
                        $value,
                        FILTER_VALIDATE_INT,
                        array("options"=> array("min_range"=>0))
                    )) {
                    throw new InvalidArgumentException(
                        'Invalid game ID.'
                    );
                }
                $this->gameId = (int)$value;
                break;
            case 'playerIdArray':
                if (!is_array($value) ||
                    count($value) !== count($this->playerIdArray)) {
                    throw new InvalidArgumentException(
                        'The number of players cannot be changed during a game.'
                    );
                }
                $this->playerIdArray = array_map('intval', $value);
                break;
            case 'activePlayerIdx':
                // require a valid index
                if (FALSE ===
                    filter_var(
                        $value,
                        FILTER_VALIDATE_INT,
                        array("options"=>
                              array("min_range"=>0,
                                    "max_range"=>count($this->playerIdArray)))
                    )) {
                    throw new InvalidArgumentException(
                        'Invalid player index.'
                    );
                }
                $this->activePlayerIdx = (int)$value;
                break;
            case 'playerWithInitiativeIdx':
                // require a valid index
                if (FALSE ===
                    filter_var(
                        $value,
                        FILTER_VALIDATE_INT,
                        array("options"=>
                            array("min_range"=>0,
                                  "max_range"=>count($this->playerIdArray)))
                    )) {
                    throw new InvalidArgumentException(
                        'Invalid player index.'
                    );
                }
                $this->playerWithInitiativeIdx = (int)$value;
                break;
            case 'buttonArray':
                if (!is_array($value) ||
                    count($value) !== count($this->playerIdArray)) {
                    throw new InvalidArgumentException(
                        'Number of buttons must equal the number of players.'
                    );
                }
                foreach ($value as $tempValueElement) {
                    if (!($tempValueElement instanceof BMButton)) {
                        throw new InvalidArgumentException(
                            'Input must be an array of BMButtons.'
                        );
                    }
                }
                $this->buttonArray = $value;
                foreach ($this->buttonArray as $playerIdx => $button) {
                    $button->playerIdx = $playerIdx;
                    $button->ownerObject = $this;
                }
                break;
            case 'activeDieArrayArray':
                if (!is_array($value)) {
                    throw new InvalidArgumentException(
                        'Active die array array must be an array.'
                    );
                }
                foreach ($value as $tempValueElement) {
                    if (!is_array($tempValueElement)) {
                        throw new InvalidArgumentException(
                            'Individual active die arrays must be arrays.'
                        );
                    }
                    foreach ($tempValueElement as $die) {
                        if (!($die instanceof BMDie)) {
                            throw new InvalidArgumentException(
                                'Elements of active die arrays must be BMDice.'
                            );
                        }
                    }
                }
                $this->activeDieArrayArray = $value;
                break;
            case 'attack':
                $value = array_values($value);
                if (!is_array($value) || (5 !== count($value))) {
                    throw new InvalidArgumentException(
                        'There must be exactly five elements in attack.'
                    );
                }
                if (!is_integer($value[0])) {
                    throw new InvalidArgumentException(
                        'The first element in attack must be an integer.'
                    );
                }
                if (!is_integer($value[1]) && !is_null($value[1])) {
                    throw new InvalidArgumentException(
                        'The second element in attack must be an integer or a NULL.'
                    );
                }
                if (!is_array($value[2]) || !is_array($value[3])) {
                    throw new InvalidArgumentException(
                        'The third and fourth elements in attack must be arrays.'
                    );
                }
                if (($value[2] !== array_filter($value[2], 'is_int')) ||
                    ($value[3] !== array_filter($value[3], 'is_int'))) {
                    throw new InvalidArgumentException(
                        'The third and fourth elements in attack must contain integers.'
                    );
                }

                if (!preg_match(
                    '/'.
                    implode('|', BMSkill::attack_types()).
                    '/',
                    $value[4]
                )) {
                    throw new InvalidArgumentException(
                        'Invalid attack type.'
                    );
                }

                if (count($value[2]) > 0 &&
                    (max($value[2]) >
                         (count($this->activeDieArrayArray[$value[0]]) - 1) ||
                     min($value[2]) < 0)) {
                    throw new LogicException(
                        'Invalid attacker attack die indices.'
                    );
                }

                if (count($value[3]) > 0 &&
                    (max($value[3]) >
                         (count($this->activeDieArrayArray[$value[1]]) - 1) ||
                     min($value[3]) < 0)) {
                    throw new LogicException(
                        'Invalid defender attack die indices.'
                    );
                }

                $this->$property = array('attackerPlayerIdx' => $value[0],
                                         'defenderPlayerIdx' => $value[1],
                                         'attackerAttackDieIdxArray' => $value[2],
                                         'defenderAttackDieIdxArray' => $value[3],
                                         'attackType' => $value[4]);
                break;
            case 'attackerAttackDieArray':
                throw new LogicException(
                    'BMGame->attackerAttackDieArray is derived from BMGame->attack.'
                );
                break;
            case 'defenderAttackDieArray':
                throw new LogicException(
                    'BMGame->defenderAttackDieArray is derived from BMGame->attack.'
                );
                break;
            case 'nRecentPasses':
                if (FALSE ===
                    filter_var(
                        $value,
                        FILTER_VALIDATE_INT,
                        array("options"=> array("min_range"=>0,
                                                "max_range"=>$this->nPlayers))
                    )) {
                    throw new InvalidArgumentException(
                        'nRecentPasses must be an integer between zero and the number of players.'
                    );
                }
                $this->nRecentPasses = $value;
                break;
            case 'capturedDieArrayArray':
                if (!is_array($value)) {
                    throw new InvalidArgumentException(
                        'Captured die array array must be an array.'
                    );
                }
                foreach ($value as $tempValueElement) {
                    if (!is_array($tempValueElement)) {
                        throw new InvalidArgumentException(
                            'Individual captured die arrays must be arrays.'
                        );
                    }
                    foreach ($tempValueElement as $tempDie) {
                        if (!($tempDie instanceof BMDie)) {
                            throw new InvalidArgumentException(
                                'Elements of captured die arrays must be BMDice.'
                            );
                        }
                    }
                }
                $this->capturedDieArrayArray = $value;
                break;
            case 'roundNumber':
                throw new LogicException(
                    'BMGame->roundNumber is derived automatically from BMGame.'
                );
                break;
            case 'roundScoreArray':
                throw new LogicException(
                    'BMGame->roundScoreArray is derived automatically from BMGame.'
                );
                break;
            case 'gameScoreArrayArray':
                $value = array_values($value);
                if (!is_array($value) ||
                    count($this->playerIdArray) !== count($value)) {
                    throw new InvalidArgumentException(
                        'There must be one game score for each player.'
                    );
                }
                $tempArray = array();
                for ($playerIdx = 0; $playerIdx < count($value); $playerIdx++) {
                    // check whether there are three inputs and they are all positive
                    if ((3 !== count($value[$playerIdx])) ||
                        min(array_map('min', $value)) < 0) {
                        throw new InvalidArgumentException(
                            'Invalid W/L/T array provided.'
                        );
                    }
                    if (array_key_exists('W', $value[$playerIdx]) &&
                        array_key_exists('L', $value[$playerIdx]) &&
                        array_key_exists('D', $value[$playerIdx])) {
                        $tempArray[$playerIdx] = array('W' => (int)$value[$playerIdx]['W'],
                                                       'L' => (int)$value[$playerIdx]['L'],
                                                       'D' => (int)$value[$playerIdx]['D']);
                    } else {
                        $tempArray[$playerIdx] = array('W' => (int)$value[$playerIdx][0],
                                                       'L' => (int)$value[$playerIdx][1],
                                                       'D' => (int)$value[$playerIdx][2]);
                    }
                }
                $this->gameScoreArrayArray = $tempArray;
                break;
            case 'maxWins':
                if (FALSE ===
                    filter_var(
                        $value,
                        FILTER_VALIDATE_INT,
                        array("options"=> array("min_range"=>1))
                    )) {
                    throw new InvalidArgumentException(
                        'maxWins must be a positive integer.'
                    );
                }
                $this->maxWins = (int)$value;
                break;
            case 'gameState':
                BMGameState::validate_game_state($value);
                $this->gameState = (int)$value;
                break;
            case 'waitingOnActionArray':
                if (!is_array($value) ||
                    count($value) !== count($this->playerIdArray)) {
                    throw new InvalidArgumentException(
                        'Number of actions must equal the number of players.'
                    );
                }
                foreach ($value as $tempValueElement) {
                    if (!is_bool($tempValueElement)) {
                        throw new InvalidArgumentException(
                            'Input must be an array of booleans.'
                        );
                    }
                }
                $this->waitingOnActionArray = $value;
                break;
            case 'autopassArray':
                if (!is_array($value) ||
                    count($value) !== count($this->playerIdArray)) {
                    throw new InvalidArgumentException(
                        'Number of settings must equal the number of players.'
                    );
                }
                foreach ($value as $tempValueElement) {
                    if (!is_bool($tempValueElement)) {
                        throw new InvalidArgumentException(
                            'Input must be an array of booleans.'
                        );
                    }
                }
                $this->autopassArray = $value;
                break;
            default:
                $this->$property = $value;
        }
    }

    public function __isset($property) {
        return isset($this->$property);
    }

    public function __unset($property) {
        if (isset($this->$property)) {
            unset($this->$property);
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function getJsonData($requestingPlayerId) {
        $requestingPlayerIdx = array_search($requestingPlayerId, $this->playerIdArray);

        $wereBothSwingValuesReset = TRUE;
        // james: need to also consider the case of many multiple draws in a row
        foreach ($this->gameScoreArrayArray as $gameScoreArray) {
            if ($gameScoreArray['W'] > 0 || $gameScoreArray['D'] > 0) {
                $wereBothSwingValuesReset = FALSE;
                break;
            }
        }

        foreach ($this->buttonArray as $button) {
            $buttonNameArray[] = $button->name;
        }

        $swingValuesAllSpecified = TRUE;
        $dieSkillsArrayArray = array();
        $diePropertiesArrayArray = array();

        if (isset($this->activeDieArrayArray)) {
            foreach ($this->activeDieArrayArray as $playerIdx => $activeDieArray) {
                if (count($activeDieArray) > 0) {
                    $dieSkillsArrayArray[$playerIdx] =
                        array_fill(0, count($activeDieArray), array());
                    $diePropertiesArrayArray[$playerIdx] =
                        array_fill(0, count($activeDieArray), array());
                }
            }

            $nDieArray = array_map('count', $this->activeDieArrayArray);
            foreach ($this->activeDieArrayArray as $playerIdx => $activeDieArray) {
                $valueArrayArray[] = array();
                $sidesArrayArray[] = array();
                $dieRecipeArrayArray[] = array();

                $playerSwingRequestArray = array();
                if (isset($this->swingRequestArrayArray[$playerIdx])) {
                    foreach ($this->swingRequestArrayArray[$playerIdx] as $swingtype => $swingdice) {
                        if ($swingdice[0] instanceof BMDieTwin) {
                            $swingdie = $swingdice[0]->dice[0];
                        } else {
                            $swingdie = $swingdice[0];
                        }
                        if ($swingdie instanceof BMDieSwing) {
                            $validRange = $swingdie->swing_range($swingtype);
                        } else {
                            throw new LogicException(
                                "Tried to put die in swingRequestArrayArray which is not a swing die: " . $swingdie
                            );
                        }
                        $playerSwingRequestArray[$swingtype] = array($validRange[0], $validRange[1]);
                    }
                }
                $swingRequestArrayArray[] = $playerSwingRequestArray;

                foreach ($activeDieArray as $dieIdx => $die) {
                    // hide swing information if appropriate
                    $dieValue = $die->value;
                    $dieMax = $die->max;
                    if (is_null($dieMax)) {
                        $swingValuesAllSpecified = FALSE;
                    }

                    if ($wereBothSwingValuesReset &&
                        ($this->gameState <= BMGameState::SPECIFY_DICE) &&
                        ($playerIdx !== $requestingPlayerIdx)) {
                        $dieValue = NULL;
                        $dieMax = NULL;
                    }
                    $valueArrayArray[$playerIdx][] = $dieValue;
                    $sidesArrayArray[$playerIdx][] = $dieMax;
                    $dieRecipeArrayArray[$playerIdx][] = $die->recipe;
                    if (count($die->skillList) > 0) {
                        foreach (array_keys($die->skillList) as $skillType) {
                            $dieSkillsArrayArray[$playerIdx][$dieIdx][$skillType] = TRUE;
                        }
                    }
                    if ($die->disabled) {
                        $diePropertiesArrayArray[$playerIdx][$dieIdx]['disabled'] = TRUE;
                    }
                }
            }
        } else {
            $nDieArray = array_fill(0, $this->nPlayers, 0);
            $valueArrayArray = array_fill(0, $this->nPlayers, array());
            $sidesArrayArray = array_fill(0, $this->nPlayers, array());
            $dieRecipeArrayArray = array_fill(0, $this->nPlayers, array());
            $swingRequestArrayArray = array_fill(0, $this->nPlayers, array());
        }

        if (isset($this->capturedDieArrayArray)) {
            $nCapturedDieArray = array_map('count', $this->capturedDieArrayArray);
            foreach ($this->capturedDieArrayArray as $playerIdx => $capturedDieArray) {
                $capturedValueArrayArray[] = array();
                $capturedSidesArrayArray[] = array();
                $capturedRecipeArrayArray[] = array();

                foreach ($capturedDieArray as $die) {
                    // hide swing information if appropriate
                    $dieValue = $die->value;
                    $dieMax = $die->max;

                    if ($wereBothSwingValuesReset &&
                        ($this->gameState <= BMGameState::SPECIFY_DICE) &&
                        ($playerIdx !== $requestingPlayerIdx)) {
                        $dieValue = NULL;
                        $dieMax = NULL;
                    }
                    $capturedValueArrayArray[$playerIdx][] = $dieValue;
                    $capturedSidesArrayArray[$playerIdx][] = $dieMax;
                    $capturedRecipeArrayArray[$playerIdx][] = $die->recipe;
                }
            }
        } else {
            $nCapturedDieArray = array_fill(0, $this->nPlayers, 0);
            $capturedValueArrayArray = array_fill(0, $this->nPlayers, array());
            $capturedSidesArrayArray = array_fill(0, $this->nPlayers, array());
            $capturedRecipeArrayArray = array_fill(0, $this->nPlayers, array());
        }

        if (!$swingValuesAllSpecified) {
            foreach ($valueArrayArray as &$valueArray) {
                foreach ($valueArray as &$value) {
                    $value = NULL;
                }
            }
        }

        // If it's someone's turn to attack, report the valid attack
        // types as part of the game data
        if ($this->gameState == BMGameState::START_TURN) {
            $validAttackTypeArray = $this->valid_attack_types();
        } else {
            $validAttackTypeArray = array();
        }

        $dataArray =
            array('gameId'                   => $this->gameId,
                  'gameState'                => $this->gameState,
                  'roundNumber'              => $this->get_roundNumber(),
                  'maxWins'                  => $this->maxWins,
                  'activePlayerIdx'          => $this->activePlayerIdx,
                  'playerWithInitiativeIdx'  => $this->playerWithInitiativeIdx,
                  'playerIdArray'            => $this->playerIdArray,
                  'buttonNameArray'          => $buttonNameArray,
                  'waitingOnActionArray'     => $this->waitingOnActionArray,
                  'nDieArray'                => $nDieArray,
                  'valueArrayArray'          => $valueArrayArray,
                  'sidesArrayArray'          => $sidesArrayArray,
                  'dieSkillsArrayArray'      => $dieSkillsArrayArray,
                  'diePropertiesArrayArray'  => $diePropertiesArrayArray,
                  'dieRecipeArrayArray'      => $dieRecipeArrayArray,
                  'nCapturedDieArray'        => $nCapturedDieArray,
                  'capturedValueArrayArray'  => $capturedValueArrayArray,
                  'capturedSidesArrayArray'  => $capturedSidesArrayArray,
                  'capturedRecipeArrayArray' => $capturedRecipeArrayArray,
                  'swingRequestArrayArray'   => $swingRequestArrayArray,
                  'validAttackTypeArray'     => $validAttackTypeArray,
                  'roundScoreArray'          => $this->get_roundScoreArray(),
                  'gameScoreArrayArray'      => $this->gameScoreArrayArray);

        return array('status' => 'ok', 'data' => $dataArray);
    }
}
