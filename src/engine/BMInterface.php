<?php

/**
 * BMInterface: interface between GUI and BMGame
 *
 * @author james
 *
 * @property-read string $message                Message intended for GUI
 * @property-read DateTime $timestamp            Timestamp of last game action
 *
 */
class BMInterface {
    // properties
    private $message;               // message intended for GUI
    private $timestamp;             // timestamp of last game action
    private static $conn = NULL;    // connection to database

    private $isTest;         // indicates if the interface is for testing



    // constructor
    public function __construct($isTest = FALSE) {
        if (!is_bool($isTest)) {
            throw new InvalidArgumentException('isTest must be boolean.');
        }

        $this->isTest = $isTest;

        if ($isTest) {
            if (file_exists('../test/src/database/mysql.test.inc.php')) {
                require_once '../test/src/database/mysql.test.inc.php';
            } else {
                require_once 'test/src/database/mysql.test.inc.php';
            }
        } else {
            require_once '../database/mysql.inc.php';
        }
        self::$conn = conn();
    }

    // methods

    public function get_player_info($playerId) {
        try {
            $query = 'SELECT * FROM player WHERE id = :id';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':id' => $playerId));
            $result = $statement->fetchAll();

            if (0 == count($result)) {
                return NULL;
            }
        } catch (Exception $e) {
            $errorData = $statement->errorInfo();
            $this->message = 'Player info get failed: ' . $errorData[2];
            return NULL;
        }

        $infoArray = $result[0];

        // set the values we want to actually return
        $playerInfoArray = array(
            'id' => (int)$infoArray['id'],
            'name_ingame' => $infoArray['name_ingame'],
            'name_irl' => $infoArray['name_irl'],
            'email' => $infoArray['email'],
            'status' => $infoArray['status'],
            'dob' => $infoArray['dob'],
            'autopass' => (bool)$infoArray['autopass'],
            'image_path' => $infoArray['image_path'],
            'comment' => $infoArray['comment'],
            'last_action_time' => $infoArray['last_action_time'],
            'creation_time' => $infoArray['creation_time'],
            'fanatic_button_id' => (int)$infoArray['fanatic_button_id'],
            'n_games_won' => (int)$infoArray['n_games_won'],
            'n_games_lost' => (int)$infoArray['n_games_lost'],
        );

        return $playerInfoArray;
    }

    public function set_player_info($playerId, array $infoArray) {
        $infoArray['autopass'] = (int)($infoArray['autopass']);
        foreach ($infoArray as $infoType => $info) {
            try {
                $query = 'UPDATE player '.
                         "SET $infoType = :info ".
                         'WHERE id = :player_id;';

                $statement = self::$conn->prepare($query);
                $statement->execute(array(':info' => $info,
                                          ':player_id' => $playerId));
                $this->message = "Player info updated successfully.";
                return array('playerId' => $playerId);
            } catch (Exception $e) {
                $this->message = 'Player info update failed: '.$e->getMessage();
            }
        }

    }

    public function create_game(
        array $playerIdArray,
        array $buttonNameArray,
        $maxWins = 3
    ) {
        // check for nonunique player ids
        if (count(array_flip($playerIdArray)) < count($playerIdArray)) {
            $this->message = 'Game create failed because a player has been selected more than once.';
            return NULL;
        }

        // validate all inputs
        foreach ($playerIdArray as $playerId) {
            if (!(is_null($playerId) || is_int($playerId))) {
                $this->message = 'Game create failed because player ID is not valid.';
                return NULL;
            }
        }

        if (FALSE ===
            filter_var(
                $maxWins,
                FILTER_VALIDATE_INT,
                array('options'=>
                      array('min_range' => 1,
                            'max_range' => 5))
            )) {
            $this->message = 'Game create failed because the maximum number of wins was invalid.';
            return NULL;
        }

        $buttonIdArray = array();
        foreach ($playerIdArray as $position => $playerId) {
            // get button ID
            $buttonName = $buttonNameArray[$position];
            if (!is_null($buttonName)) {
                $query = 'SELECT id FROM button '.
                         'WHERE name = :button_name';
                $statement = self::$conn->prepare($query);
                $statement->execute(array(':button_name' => $buttonName));
                $fetchData = $statement->fetch();
                if (FALSE === $fetchData) {
                    $this->message = 'Game create failed because a button name was not valid.';
                    return NULL;
                }
                $buttonIdArray[] = $fetchData[0];
            } else {
                $buttonIdArray[] = NULL;
            }
        }

        try {
            // create basic game details
            $query = 'INSERT INTO game '.
                     '    (status_id, '.
                     '     n_players, '.
                     '     n_target_wins, '.
                     '     n_recent_passes, '.
                     '     creator_id) '.
                     'VALUES '.
                     '    ((SELECT id FROM game_status WHERE name = :status), '.
                     '     :n_players, '.
                     '     :n_target_wins, '.
                     '     :n_recent_passes, '.
                     '     :creator_id)';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':status'        => 'OPEN',
                                      ':n_players'     => count($playerIdArray),
                                      ':n_target_wins' => $maxWins,
                                      ':n_recent_passes' => 0,
                                      ':creator_id'    => $playerIdArray[0]));

            $statement = self::$conn->prepare('SELECT LAST_INSERT_ID()');
            $statement->execute();
            $fetchData = $statement->fetch();
            $gameId = (int)$fetchData[0];

            foreach ($playerIdArray as $position => $playerId) {
                // add info to game_player_map
                $query = 'INSERT INTO game_player_map '.
                         '(game_id, player_id, button_id, position) '.
                         'VALUES '.
                         '(:game_id, :player_id, :button_id, :position)';
                $statement = self::$conn->prepare($query);

                $statement->execute(array(':game_id'   => $gameId,
                                          ':player_id' => $playerId,
                                          ':button_id' => $buttonIdArray[$position],
                                          ':position'  => $position));
            }

            // update game state to latest possible
            $game = $this->load_game($gameId);
            if (!($game instanceof BMGame)) {
                throw new Exception(
                    "Could not load newly-created game $gameId"
                );
            }
            $this->save_game($game);

            $this->message = "Game $gameId created successfully.";
            return array('gameId' => $gameId);
        } catch (Exception $e) {
            // Failure might occur on DB insert or on the subsequent load
            $errorData = $statement->errorInfo();
            if ($errorData[2]) {
                $this->message = 'Game create failed: ' . $errorData[2];
            } else {
                $this->message = 'Game create failed: ' . $e->getMessage();
            }
            error_log(
                "Caught exception in BMInterface::create_game: " .
                $e->getMessage()
            );
            return NULL;
        }
    }

    public function load_game($gameId) {
        try {
            // check that the gameId exists
            $query = 'SELECT g.*,'.
                     'v.player_id, v.position, v.autopass,'.
                     'v.button_name, v.alt_recipe,'.
                     'v.n_rounds_won, v.n_rounds_lost, v.n_rounds_drawn,'.
                     'v.did_win_initiative,'.
                     'v.is_awaiting_action '.
                     'FROM game AS g '.
                     'LEFT JOIN game_player_view AS v '.
                     'ON g.id = v.game_id '.
                     'WHERE game_id = :game_id '.
                     'ORDER BY game_id;';
            $statement1 = self::$conn->prepare($query);
            $statement1->execute(array(':game_id' => $gameId));

            while ($row = $statement1->fetch()) {
                // load game attributes
                if (!isset($game)) {
                    $game = new BMGame;
                    $game->gameId    = $gameId;
                    $game->gameState = $row['game_state'];
                    $game->maxWins   = $row['n_target_wins'];
                    $game->turnNumberInRound = $row['turn_number_in_round'];
                    $game->nRecentPasses = $row['n_recent_passes'];
                    $this->timestamp = new DateTime($row['last_action_time']);
                }

                $pos = $row['position'];
                $playerIdArray[$pos] = $row['player_id'];
                $autopassArray[$pos] = (bool)$row['autopass'];

                if (1 == $row['did_win_initiative']) {
                    $game->playerWithInitiativeIdx = $pos;
                }

                $gameScoreArrayArray[$pos] = array($row['n_rounds_won'],
                                                   $row['n_rounds_lost'],
                                                   $row['n_rounds_drawn']);

                // load button attributes
                if (isset($row['alt_recipe'])) {
                    $recipe = $row['alt_recipe'];
                } else {
                    $recipe = $this->get_button_recipe_from_name($row['button_name']);
                }
                if (isset($recipe)) {
                    $button = new BMButton;
                    $button->load($recipe, $row['button_name']);
                    if (isset($row['alt_recipe'])) {
                        $button->hasAlteredRecipe = TRUE;
                    }
                    $buttonArray[$pos] = $button;
                } else {
                    throw new InvalidArgumentException('Invalid button name.');
                }

                // load player attributes
                switch ($row['is_awaiting_action']) {
                    case 1:
                        $waitingOnActionArray[$pos] = TRUE;
                        break;
                    case 0:
                        $waitingOnActionArray[$pos] = FALSE;
                        break;
                }

                if ($row['current_player_id'] == $row['player_id']) {
                    $game->activePlayerIdx = $pos;
                }

                if ($row['did_win_initiative']) {
                    $game->playerWithInitiativeIdx = $pos;
                }
            }

            // check whether the game exists
            if (!isset($game)) {
                $this->message = "Game $gameId does not exist.";
                return FALSE;
            }

            // fill up the game object with the database data
            $game->playerIdArray = $playerIdArray;
            $game->gameScoreArrayArray = $gameScoreArrayArray;
            $game->buttonArray = $buttonArray;
            $game->waitingOnActionArray = $waitingOnActionArray;
            $game->autopassArray = $autopassArray;

            // add swing values
            $game->swingValueArrayArray = array_fill(0, $game->nPlayers, array());
            $query = 'SELECT * '.
                     'FROM game_swing_map '.
                     'WHERE game_id = :game_id ';
            $statement2 = self::$conn->prepare($query);
            $statement2->execute(array(':game_id' => $gameId));
            while ($row = $statement2->fetch()) {
                $playerIdx = array_search($row['player_id'], $game->playerIdArray);
                $game->swingValueArrayArray[$playerIdx][$row['swing_type']] = $row['swing_value'];
            }

            // add die attributes
            $query = 'SELECT d.*,'.
                     '       s.name AS status '.
                     'FROM die AS d '.
                     'LEFT JOIN die_status AS s '.
                     'ON d.status_id = s.id '.
                     'WHERE game_id = :game_id '.
                     'ORDER BY id;';

            $statement3 = self::$conn->prepare($query);
            $statement3->execute(array(':game_id' => $gameId));

            $activeDieArrayArray = array_fill(0, count($playerIdArray), array());
            $captDieArrayArray = array_fill(0, count($playerIdArray), array());

            while ($row = $statement3->fetch()) {
                $playerIdx = array_search($row['owner_id'], $game->playerIdArray);

                $die = BMDie::create_from_recipe($row['recipe']);
                $die->playerIdx = $playerIdx;
                if (isset($row['value'])) {
                    $die->value = (int)$row['value'];
                }
                $originalPlayerIdx = array_search(
                    $row['original_owner_id'],
                    $game->playerIdArray
                );
                $die->originalPlayerIdx = $originalPlayerIdx;
                $die->ownerObject = $game;

                if (isset($die->swingType)) {
                    $game->request_swing_values($die, $die->swingType, $originalPlayerIdx);

                    if (isset($row['swing_value'])) {
                        $swingSetSuccess = $die->set_swingValue($game->swingValueArrayArray[$originalPlayerIdx]);
                        if (!$swingSetSuccess) {
                            throw new LogicException('Swing value set failed.');
                        }
                    }
                }

                switch ($row['status']) {
                    case 'NORMAL':
                        $activeDieArrayArray[$playerIdx][$row['position']] = $die;
                        break;
                    case 'SELECTED':
                        $die->selected = TRUE;
                        $activeDieArrayArray[$playerIdx][$row['position']] = $die;
                        break;
                    case 'DISABLED':
                        $die->disabled = TRUE;
                        $activeDieArrayArray[$playerIdx][$row['position']] = $die;
                        break;
                    case 'DIZZY':
                        $die->dizzy = TRUE;
                        $activeDieArrayArray[$playerIdx][$row['position']] = $die;
                        break;
                    case 'CAPTURED':
                        $die->captured = TRUE;
                        $captDieArrayArray[$playerIdx][$row['position']] = $die;
                        break;
                }
            }

            $game->activeDieArrayArray = $activeDieArrayArray;
            $game->capturedDieArrayArray = $captDieArrayArray;

            $this->message = $this->message."Loaded data for game $gameId.";

            return $game;
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::load_game: " .
                $e->getMessage()
            );
            $this->message = "Game load failed: $e";
            return NULL;
        }
    }

    public function save_game(BMGame $game) {
        // force game to proceed to the latest possible before saving
        $game->proceed_to_next_user_action();

        try {
            if (is_null($game->activePlayerIdx)) {
                $currentPlayerId = NULL;
            } else {
                $currentPlayerId = $game->playerIdArray[$game->activePlayerIdx];
            }

            if (BMGameState::END_GAME == $game->gameState) {
                $status = 'COMPLETE';
            } elseif (in_array(0, $game->playerIdArray) ||
                      in_array(NULL, $game->buttonArray)) {
                $status = 'OPEN';
            } else {
                $status = 'ACTIVE';
            }

            // game
            $query = 'UPDATE game '.
                     'SET last_action_time = NOW(),'.
                     '    status_id = '.
                     '        (SELECT id FROM game_status WHERE name = :status),'.
                     '    game_state = :game_state,'.
                     '    round_number = :round_number,'.
                     '    turn_number_in_round = :turn_number_in_round,'.
            //:n_recent_draws
                     '    n_recent_passes = :n_recent_passes,'.
                     '    current_player_id = :current_player_id '.
            //:last_winner_id
            //:tournament_id
            //:description
            //:chat
                     'WHERE id = :game_id;';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':status' => $status,
                                      ':game_state' => $game->gameState,
                                      ':round_number' => $game->roundNumber,
                                      ':turn_number_in_round' => $game->turnNumberInRound,
                                      ':n_recent_passes' => $game->nRecentPasses,
                                      ':current_player_id' => $currentPlayerId,
                                      ':game_id' => $game->gameId));

            // button recipes if altered
            if (isset($game->buttonArray)) {
                foreach ($game->buttonArray as $playerIdx => $button) {
                    if ($button->hasAlteredRecipe) {
                        $query = 'UPDATE game_player_map '.
                                 'SET alt_recipe = :alt_recipe '.
                                 'WHERE game_id = :game_id '.
                                 'AND player_id = :player_id;';
                        $statement = self::$conn->prepare($query);
                        $statement->execute(array(':alt_recipe' => $button->recipe,
                                                  ':game_id' => $game->gameId,
                                                  ':player_id' => $game->playerIdArray[$playerIdx]));
                    }
                }
            }

            // set round scores
            if (isset($game->gameScoreArrayArray)) {
                foreach ($game->playerIdArray as $playerIdx => $playerId) {
                    $query = 'UPDATE game_player_map '.
                             'SET n_rounds_won = :n_rounds_won,'.
                             '    n_rounds_lost = :n_rounds_lost,'.
                             '    n_rounds_drawn = :n_rounds_drawn '.
                             'WHERE game_id = :game_id '.
                             'AND player_id = :player_id;';
                    $statement = self::$conn->prepare($query);
                    $statement->execute(array(':n_rounds_won' => $game->gameScoreArrayArray[$playerIdx]['W'],
                                              ':n_rounds_lost' => $game->gameScoreArrayArray[$playerIdx]['L'],
                                              ':n_rounds_drawn' => $game->gameScoreArrayArray[$playerIdx]['D'],
                                              ':game_id' => $game->gameId,
                                              ':player_id' => $playerId));
                }
            }

            // set swing values
            $query = 'DELETE FROM game_swing_map '.
                     'WHERE game_id = :game_id;';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':game_id' => $game->gameId));

            if (isset($game->swingValueArrayArray)) {
                foreach ($game->playerIdArray as $playerIdx => $playerId) {
                    if (!array_key_exists($playerIdx, $game->swingValueArrayArray)) {
                        continue;
                    }
                    $swingValueArray = $game->swingValueArrayArray[$playerIdx];
                    if (isset($swingValueArray)) {
                        foreach ($swingValueArray as $swingType => $swingValue) {
                            $query = 'INSERT INTO game_swing_map '.
                                     '(game_id, player_id, swing_type, swing_value) '.
                                     'VALUES '.
                                     '(:game_id, :player_id, :swing_type, :swing_value)';
                            $statement = self::$conn->prepare($query);
                            $statement->execute(array(':game_id'     => $game->gameId,
                                                      ':player_id'   => $playerId,
                                                      ':swing_type'  => $swingType,
                                                      ':swing_value' => $swingValue));
                        }
                    }

                }
            }

            // set player that won initiative
            if (isset($game->playerWithInitiativeIdx)) {
                // set all players to not having initiative
                $query = 'UPDATE game_player_map '.
                         'SET did_win_initiative = 0 '.
                         'WHERE game_id = :game_id;';
                $statement = self::$conn->prepare($query);
                $statement->execute(array(':game_id' => $game->gameId));

                // set player that won initiative
                $query = 'UPDATE game_player_map '.
                         'SET did_win_initiative = 1 '.
                         'WHERE game_id = :game_id '.
                         'AND player_id = :player_id;';
                $statement = self::$conn->prepare($query);
                $statement->execute(array(':game_id' => $game->gameId,
                                          ':player_id' => $game->playerIdArray[$game->playerWithInitiativeIdx]));
            }


            // set players awaiting action
            foreach ($game->waitingOnActionArray as $playerIdx => $waitingOnAction) {
                $query = 'UPDATE game_player_map '.
                         'SET is_awaiting_action = :is_awaiting_action '.
                         'WHERE game_id = :game_id '.
                         'AND player_id = :player_id;';
                $statement = self::$conn->prepare($query);
                if ($waitingOnAction) {
                    $is_awaiting_action = 1;
                } else {
                    $is_awaiting_action = 0;
                }
                $statement->execute(array(':is_awaiting_action' => $is_awaiting_action,
                                          ':game_id' => $game->gameId,
                                          ':player_id' => $game->playerIdArray[$playerIdx]));
            }

            // set existing dice to have a status of DELETED and get die ids
            //
            // note that the logic is written this way to make debugging easier
            // in case something fails during the addition of dice
            $query = 'UPDATE die '.
                     'SET status_id = '.
                     '    (SELECT id FROM die_status WHERE name = "DELETED") '.
                     'WHERE game_id = :game_id;';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':game_id' => $game->gameId));

            // add active dice to table 'die'
            if (isset($game->activeDieArrayArray)) {
                foreach ($game->activeDieArrayArray as $playerIdx => $activeDieArray) {
                    foreach ($activeDieArray as $dieIdx => $activeDie) {
                        // james: set status, this is currently INCOMPLETE
                        $status = 'NORMAL';
                        if ($activeDie->selected) {
                            $status = 'SELECTED';
                        } elseif ($activeDie->disabled) {
                            $status = 'DISABLED';
                        } elseif ($activeDie->dizzy) {
                            $status = 'DIZZY';
                        }

                        $this->db_insert_die($game, $playerIdx, $activeDie, $status, $dieIdx);
                    }
                }
            }

            // add captured dice to table 'die'
            if (isset($game->capturedDieArrayArray)) {
                foreach ($game->capturedDieArrayArray as $playerIdx => $activeDieArray) {
                    foreach ($activeDieArray as $dieIdx => $activeDie) {
                        // james: set status, this is currently INCOMPLETE
                        $status = 'CAPTURED';

                        $this->db_insert_die($game, $playerIdx, $activeDie, $status, $dieIdx);
                    }
                }
            }

            // delete dice with a status of "DELETED" for this game
            $query = 'DELETE FROM die '.
                     'WHERE status_id = '.
                     '    (SELECT id FROM die_status WHERE name = "DELETED") '.
                     'AND game_id = :game_id;';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':game_id' => $game->gameId));

            // If any game action entries were generated, load them
            // into the message so the calling player can see them,
            // then save them to the historical log
            if (count($game->actionLog) > 0) {
                $this->load_message_from_game_actions($game);
                $this->log_game_actions($game);
            }
            // If the player sent a chat message, insert it now
            // then save them to the historical log
            if ($game->chat['chat']) {
                $this->log_game_chat($game);
            }

        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::save_game: " .
                $e->getMessage()
            );
            $this->message = "Game save failed: $e";
        }
    }

    // Actually insert a die into the database - all error checking to be done by caller
    protected function db_insert_die($game, $playerIdx, $activeDie, $status, $dieIdx) {
        $query = 'INSERT INTO die '.
                 '    (owner_id, '.
                 '     original_owner_id, '.
                 '     game_id, '.
                 '     status_id, '.
                 '     recipe, '.
                 '     swing_value, '.
                 '     position, '.
                 '     value) '.
                 'VALUES '.
                 '    (:owner_id, '.
                 '     :original_owner_id, '.
                 '     :game_id, '.
                 '     (SELECT id FROM die_status WHERE name = :status), '.
                 '     :recipe, '.
                 '     :swing_value, '.
                 '     :position, '.
                 '     :value);';
        $statement = self::$conn->prepare($query);
        $statement->execute(array(':owner_id' => $game->playerIdArray[$playerIdx],
                                  ':original_owner_id' => $game->playerIdArray[$activeDie->originalPlayerIdx],
                                  ':game_id' => $game->gameId,
                                  ':status' => $status,
                                  ':recipe' => $activeDie->recipe,
                                  ':swing_value' => $activeDie->swingValue,
                                  ':position' => $dieIdx,
                                  ':value' => $activeDie->value));
    }

    // Get all player games (either active or inactive) from the database
    // No error checking - caller must do it
    protected function get_all_games($playerId, $getActiveGames) {

        // the following SQL logic assumes that there are only two players per game
        $query = 'SELECT v1.game_id,'.
                 'v1.player_id AS opponent_id,'.
                 'v1.player_name AS opponent_name,'.
                 'v2.button_name AS my_button_name,'.
                 'v1.button_name AS opponent_button_name,'.
                 'v2.n_rounds_won AS n_wins,'.
                 'v2.n_rounds_drawn AS n_draws,'.
                 'v1.n_rounds_won AS n_losses,'.
                 'v1.n_target_wins,'.
                 'v2.is_awaiting_action,'.
                 'g.game_state,'.
                 's.name AS status '.
                 'FROM game_player_view AS v1 '.
                 'LEFT JOIN game_player_view AS v2 '.
                 'ON v1.game_id = v2.game_id '.
                 'LEFT JOIN game AS g '.
                 'ON g.id = v1.game_id '.
                 'LEFT JOIN game_status AS s '.
                 'ON g.status_id = s.id '.
                 'WHERE v2.player_id = :player_id '.
                 'AND v1.player_id != v2.player_id ';
        if ($getActiveGames) {
            $query .= 'AND s.name != "COMPLETE" ';
        } else {
            $query .= 'AND s.name = "COMPLETE" ';
        }
        $query .= 'ORDER BY v1.game_id;';
        $statement = self::$conn->prepare($query);
        $statement->execute(array(':player_id' => $playerId));

        // Initialize the arrays
        $gameIdArray = array();
        $opponentIdArray = array();
        $opponentNameArray = array();
        $myButtonNameArray = array();
        $oppButtonNameArray = array();
        $nWinsArray = array();
        $nDrawsArray = array();
        $nLossesArray = array();
        $nTargetWinsArray = array();
        $isToActArray = array();
        $gameStateArray = array();
        $statusArray = array();

        while ($row = $statement->fetch()) {
            $gameIdArray[]        = (int)$row['game_id'];
            $opponentIdArray[]    = (int)$row['opponent_id'];
            $opponentNameArray[]  = $row['opponent_name'];
            $myButtonNameArray[]  = $row['my_button_name'];
            $oppButtonNameArray[] = $row['opponent_button_name'];
            $nWinsArray[]         = (int)$row['n_wins'];
            $nDrawsArray[]        = (int)$row['n_draws'];
            $nLossesArray[]       = (int)$row['n_losses'];
            $nTargetWinsArray[]   = (int)$row['n_target_wins'];
            $isToActArray[]       = (int)$row['is_awaiting_action'];
            $gameStateArray[]     = BMGameState::as_string($row['game_state']);
            $statusArray[]        = $row['status'];
        }

        return array('gameIdArray'             => $gameIdArray,
                     'opponentIdArray'         => $opponentIdArray,
                     'opponentNameArray'       => $opponentNameArray,
                     'myButtonNameArray'       => $myButtonNameArray,
                     'opponentButtonNameArray' => $oppButtonNameArray,
                     'nWinsArray'              => $nWinsArray,
                     'nDrawsArray'             => $nDrawsArray,
                     'nLossesArray'            => $nLossesArray,
                     'nTargetWinsArray'        => $nTargetWinsArray,
                     'isAwaitingActionArray'   => $isToActArray,
                     'gameStateArray'          => $gameStateArray,
                     'statusArray'             => $statusArray);
    }

    public function get_all_active_games($playerId) {
        try {
            $this->message = 'All game details retrieved successfully.';
            return $this->get_all_games($playerId, TRUE);
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::get_all_active_games: " .
                $e->getMessage()
            );
            $this->message = 'Game detail get failed.';
            return NULL;
        }
    }

    public function get_all_completed_games($playerId) {
        try {
            $this->message = 'All game details retrieved successfully.';
            return $this->get_all_games($playerId, FALSE);
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::get_all_active_games: " .
                $e->getMessage()
            );
            $this->message = 'Game detail get failed.';
            return NULL;
        }
    }

    public function get_all_button_names() {
        try {
            // if the site is production, don't report unimplemented buttons at all
            $site_type = $this->get_config('site_type');

            $statement = self::$conn->prepare('SELECT name, recipe, btn_special FROM button_view');
            $statement->execute();

            // Look for unimplemented skills in each button definition.
            // If we get an exception while checking, assume there's
            // an unimplemented skill
            while ($row = $statement->fetch()) {
                try {
                    $button = new BMButton();
                    $button->load($row['recipe'], $row['name']);

                    $standardName = preg_replace('/[^a-zA-Z0-9]/', '', $button->name);
                    if ((1 == $row['btn_special']) &&
                        !class_exists('BMBtnSkill'.$standardName)) {
                        $button->hasUnimplementedSkill = TRUE;
                    }

                    $hasUnimplSkill = $button->hasUnimplementedSkill;
                } catch (Exception $e) {
                    $hasUnimplSkill = TRUE;
                }

                if (($site_type != 'production') || (!($hasUnimplSkill))) {
                    $buttonNameArray[] = $row['name'];
                    $recipeArray[] = $row['recipe'];
                    $hasUnimplSkillArray[] = $hasUnimplSkill;
                }
            }
            $this->message = 'All button names retrieved successfully.';
            return array('buttonNameArray'            => $buttonNameArray,
                         'recipeArray'                => $recipeArray,
                         'hasUnimplementedSkillArray' => $hasUnimplSkillArray);
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::get_all_button_names: " .
                $e->getMessage()
            );
            $this->message = 'Button name get failed.';
            return NULL;
        }
    }

    public function get_button_recipe_from_name($name) {
        try {
            $query = 'SELECT recipe FROM button_view '.
                     'WHERE name = :name';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':name' => $name));

            $row = $statement->fetch();
            return($row['recipe']);
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::get_button_recipe_from_name: "
                . $e->getMessage()
            );
            $this->message = 'Button recipe get failed.';
        }
    }

    public function get_player_names_like($input = '') {
        try {
            $query = 'SELECT name_ingame,status FROM player '.
                     'WHERE name_ingame LIKE :input '.
                     'ORDER BY name_ingame';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':input' => $input.'%'));

            $nameArray = array();
            $statusArray = array();
            while ($row = $statement->fetch()) {
                $nameArray[] = $row['name_ingame'];
                $statusArray[] = $row['status'];
            }
            $this->message = 'Names retrieved successfully.';
            return array('nameArray' => $nameArray, 'statusArray' => $statusArray);
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::get_player_names_like: " .
                $e->getMessage()
            );
            $this->message = 'Player name get failed.';
            return NULL;
        }
    }

    public function get_player_id_from_name($name) {
        try {
            $query = 'SELECT id FROM player '.
                     'WHERE name_ingame = :input';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':input' => $name));
            $result = $statement->fetch();
            if (!$result) {
                $this->message = 'Player name does not exist.';
                return('');
            } else {
                $this->message = 'Player ID retrieved successfully.';
                return((int)$result[0]);
            }
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::get_player_id_from_name: " .
                $e->getMessage()
            );
            $this->message = 'Player ID get failed.';
        }
    }

    public function get_player_name_from_id($playerId) {
        try {
            $query = 'SELECT name_ingame FROM player '.
                     'WHERE id = :id';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':id' => $playerId));
            $result = $statement->fetch();
            if (!$result) {
                $this->message = 'Player ID does not exist.';
                return('');
            } else {
                return($result[0]);
            }
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::get_player_name_from_id: " .
                $e->getMessage()
            );
            $this->message = 'Player name get failed.';
        }
    }

    public function get_player_name_mapping($game) {
        $idNameMapping = array();
        foreach ($game->playerIdArray as $playerId) {
            $idNameMapping[$playerId] = $this->get_player_name_from_id($playerId);
        }
        return $idNameMapping;
    }

    // Check whether a requested action still needs to be taken.
    // If the time stamp is not important, use the string 'ignore'
    // for $postedTimestamp.
    protected function is_action_current(
        BMGame $game,
        $expectedGameState,
        $postedTimestamp,
        $roundNumber,
        $currentPlayerId
    ) {
        $currentPlayerIdx = array_search($currentPlayerId, $game->playerIdArray);

        if (FALSE === $currentPlayerIdx) {
            $this->message = 'You are not a participant in this game';
            return FALSE;
        }

        if (FALSE === $game->waitingOnActionArray[$currentPlayerIdx]) {
            $this->message = 'You are not the active player';
            return FALSE;
        };

        $doesTimeStampAgree =
            ('ignore' === $postedTimestamp) ||
            ($postedTimestamp == $this->timestamp->format(DATE_RSS));
        $doesRoundNumberAgree =
            ('ignore' === $roundNumber) ||
            ($roundNumber == $game->roundNumber);
        $doesGameStateAgree = $expectedGameState == $game->gameState;

        $this->message = 'Game state is not current';
        return ($doesTimeStampAgree &&
                $doesRoundNumberAgree &&
                $doesGameStateAgree);
    }

    // Enter recent game actions into the action log
    // Note: it might be possible for this to be a protected function
    public function log_game_actions(BMGame $game) {
        $query = 'INSERT INTO game_action_log ' .
                 '(game_id, game_state, action_type, acting_player, message) ' .
                 'VALUES ' .
                 '(:game_id, :game_state, :action_type, :acting_player, :message)';
        foreach ($game->actionLog as $gameAction) {
            $statement = self::$conn->prepare($query);
            $statement->execute(
                array(':game_id'     => $game->gameId,
                      ':game_state' => $gameAction->gameState,
                      ':action_type' => $gameAction->actionType,
                      ':acting_player' => $gameAction->actingPlayerId,
                      ':message'    => json_encode($gameAction->params))
            );
        }
        $game->empty_action_log();
    }

    public function load_game_action_log(BMGame $game, $n_entries = 10) {
        try {
            $query = 'SELECT action_time,game_state,action_type,acting_player,message FROM game_action_log ' .
                     'WHERE game_id = :game_id ORDER BY id DESC LIMIT ' . $n_entries;
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':game_id' => $game->gameId));
            $logEntries = array();
            $playerIdNames = $this->get_player_name_mapping($game);
            while ($row = $statement->fetch()) {
                $params = json_decode($row['message'], $assoc = TRUE);
                if (!($params)) {
                    $params = $row['message'];
                }
                $gameAction = new BMGameAction(
                    $row['game_state'],
                    $row['action_type'],
                    $row['acting_player'],
                    $params
                );

                // Only add the message to the log if one is returned: friendly_message() may
                // intentionally return no message if providing one would leak information
                $message = $gameAction->friendly_message($playerIdNames, $game->roundNumber, $game->gameState);
                if ($message) {
                    $logEntries[] = array(
                        'timestamp' => $row['action_time'],
                        'message' => $message,
                    );
                }
            }
            return $logEntries;
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::load_game_action_log: " .
                $e->getMessage()
            );
            $this->message = 'Internal error while reading log entries';
            return NULL;
        }
    }

    // Create a status message based on recent game actions
    private function load_message_from_game_actions(BMGame $game) {
        $this->message = '';
        $playerIdNames = $this->get_player_name_mapping($game);
        foreach ($game->actionLog as $gameAction) {
            $this->message .= $gameAction->friendly_message(
                $playerIdNames,
                $game->roundNumber,
                $game->gameState
            ) . '. ';
        }
    }

    protected function log_game_chat(BMGame $game) {

        // We're going to display this in user browsers, so first clean up all HTML tags
        $mysqlchat = $game->chat['chat'];

        // Now, if the string is too long, truncate it
        if (strlen($mysqlchat) > 1020) {
            $mysqlchat = substr($mysqlchat, 0, 1020);
        }

        $query = 'INSERT INTO game_chat_log ' .
                 '(game_id, chatting_player, message) ' .
                 'VALUES ' .
                 '(:game_id, :chatting_player, :message)';
        $statement = self::$conn->prepare($query);
        $statement->execute(
            array(':game_id'         => $game->gameId,
                  ':chatting_player' => $game->chat['playerIdx'],
                  ':message'         => $mysqlchat)
        );
    }

    public function load_game_chat_log(BMGame $game, $n_entries = 5) {
        try {
            $query = 'SELECT chat_time,chatting_player,message FROM game_chat_log ' .
                     'WHERE game_id = :game_id ORDER BY id DESC LIMIT ' . $n_entries;
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':game_id' => $game->gameId));
            $chatEntries = array();
            while ($row = $statement->fetch()) {
                $chatEntries[] = array(
                    'timestamp' => $row['chat_time'],
                    'player' => $this->get_player_name_from_id($row['chatting_player']),
                    'message' => $row['message'],
                );
            }
            return $chatEntries;
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::load_game_chat_log: " .
                $e->getMessage()
            );
            $this->message = 'Internal error while reading chat entries';
            return NULL;
        }
    }

    public function submit_swing_values(
        $playerId,
        $gameId,
        $roundNumber,
        $swingValueArray
    ) {
        try {
            $game = $this->load_game($gameId);
            $currentPlayerIdx = array_search($playerId, $game->playerIdArray);

            // check that the timestamp and the game state are correct, and that
            // the swing values still need to be set
            if (!$this->is_action_current(
                $game,
                BMGameState::SPECIFY_DICE,
                'ignore',
                $roundNumber,
                $playerId
            )) {
                $this->message = 'Swing dice no longer need to be set';
                return NULL;
            }

            // try to set swing values
            $swingRequested = array_keys($game->swingRequestArrayArray[$currentPlayerIdx]);
            sort($swingRequested);
            $swingSubmitted = array_keys($swingValueArray);
            sort($swingSubmitted);

            if ($swingRequested != $swingSubmitted) {
                $this->message = 'Wrong swing values submitted: expected ' . implode(',', $swingRequested);
                return NULL;
            }

            $game->swingValueArrayArray[$currentPlayerIdx] = $swingValueArray;

            $game->proceed_to_next_user_action();

            // check for successful swing value set
            if ((FALSE == $game->waitingOnActionArray[$currentPlayerIdx]) ||
                ($game->gameState > BMGameState::SPECIFY_DICE) ||
                ($game->roundNumber > $roundNumber)) {
                $game->log_action(
                    'choose_swing',
                    $game->playerIdArray[$currentPlayerIdx],
                    array(
                        'roundNumber' => $game->roundNumber,
                        'swingValues' => $swingValueArray,
                    )
                );
                $this->save_game($game);
                $this->message = 'Successfully set swing values';
                return TRUE;
            } else {
                if ($game->message) {
                    $this->message = $game->message;
                } else {
                    $this->message = 'Failed to set swing values';
                }
                return NULL;
            }
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::submit_swing_values: " .
                $e->getMessage()
            );
            $this->message = 'Internal error while setting swing values';
        }
    }

    public function submit_turn(
        $playerId,
        $gameId,
        $roundNumber,
        $submitTimestamp,
        $dieSelectStatus,
        $attackType,
        $attackerIdx,
        $defenderIdx,
        $chat
    ) {
        try {
            $game = $this->load_game($gameId);
            if (!$this->is_action_current(
                $game,
                BMGameState::START_TURN,
                $submitTimestamp,
                $roundNumber,
                $playerId
            )) {
                $this->message = 'It is not your turn to attack right now';
                return NULL;
            }

            // N.B. dieSelectStatus should contain boolean values of whether each
            // die is selected, starting with attacker dice and concluding with
            // defender dice

            // attacker and defender indices are provided in POST
            $attackers = array();
            $defenders = array();
            $attackerDieIdx = array();
            $defenderDieIdx = array();

            // divide selected dice up into attackers and defenders
            $nAttackerDice = count($game->activeDieArrayArray[$attackerIdx]);
            $nDefenderDice = count($game->activeDieArrayArray[$defenderIdx]);

            for ($dieIdx = 0; $dieIdx < $nAttackerDice; $dieIdx++) {
                if (filter_var(
                    $dieSelectStatus["playerIdx_{$attackerIdx}_dieIdx_{$dieIdx}"],
                    FILTER_VALIDATE_BOOLEAN
                )) {
                    $attackers[] = $game->activeDieArrayArray[$attackerIdx][$dieIdx];
                    $attackerDieIdx[] = $dieIdx;
                }
            }

            for ($dieIdx = 0; $dieIdx < $nDefenderDice; $dieIdx++) {
                if (filter_var(
                    $dieSelectStatus["playerIdx_{$defenderIdx}_dieIdx_{$dieIdx}"],
                    FILTER_VALIDATE_BOOLEAN
                )) {
                    $defenders[] = $game->activeDieArrayArray[$defenderIdx][$dieIdx];
                    $defenderDieIdx[] = $dieIdx;
                }
            }

            // populate BMAttack object for the specified attack
            $game->attack = array($attackerIdx, $defenderIdx,
                                  $attackerDieIdx, $defenderDieIdx,
                                  $attackType);
            $attack = BMAttack::get_instance($attackType);

            foreach ($attackers as $attackDie) {
                $attack->add_die($attackDie);
            }

            $game->add_chat($playerId, $chat);

            // validate the attack and output the result
            if ($attack->validate_attack($game, $attackers, $defenders)) {
                $this->save_game($game);

                // On success, don't set a message, because one will be set from the action log
                return TRUE;
            } else {
                $this->message = 'Requested attack is not valid';
                return NULL;
            }
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::submit_turn: " .
                $e->getMessage()
            );
            $this->message = 'Internal error while submitting turn';
        }
    }

    // react_to_auxiliary expects the following inputs:
    //
    //   $action:
    //       One of {'add', 'decline'}.
    //
    //   $dieIdx:
    //       (i)  If this is an 'add' action, then this is the die index of the
    //            die to be added.
    //       (ii) If this is a 'decline' action, then this will be ignored.
    //
    // The function returns a boolean telling whether the reaction has been
    // successful.
    // If it fails, $this->message will say why it has failed.

    public function react_to_auxiliary(
        $playerId,
        $gameId,
        $action,
        $dieIdx = NULL
    ) {
        try {
            $game = $this->load_game($gameId);
            if (!$this->is_action_current(
                $game,
                BMGameState::CHOOSE_AUXILIARY_DICE,
                'ignore',
                'ignore',
                $playerId
            )) {
                return FALSE;
            }

            $playerIdx = array_search($playerId, $game->playerIdArray);

            switch ($action) {
                case 'add':
                    if (!array_key_exists($dieIdx, $game->activeDieArrayArray[$playerIdx]) ||
                        !$game->activeDieArrayArray[$playerIdx][$dieIdx]->has_skill('Auxiliary')) {
                        $this->message = 'Invalid auxiliary choice';
                        return FALSE;
                    }
                    $die = $game->activeDieArrayArray[$playerIdx][$dieIdx];
                    $die->selected = TRUE;
                    $waitingOnActionArray = $game->waitingOnActionArray;
                    $waitingOnActionArray[$playerIdx] = FALSE;
                    $game->waitingOnActionArray = $waitingOnActionArray;
                    $game->log_action(
                        'add_auxiliary',
                        $game->playerIdArray[$playerIdx],
                        array(
                            'roundNumber' => $game->roundNumber,
                            'die' => $die->get_action_log_data(),
                        )
                    );
                    $this->message = 'Auxiliary die chosen successfully';
                    break;
                case 'decline':
                    $game->waitingOnActionArray = array_fill(0, $game->nPlayers, FALSE);
                    $game->log_action(
                        'decline_auxiliary',
                        $game->playerIdArray[$playerIdx],
                        array('declineAuxiliary' => TRUE)
                    );
                    $this->message = 'Declined auxiliary dice';
                    break;
                default:
                    $this->message = 'Invalid response to auxiliary choice.';
                    return FALSE;
            }
            $this->save_game($game);

            return TRUE;
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::react_to_auxiliary: " .
                $e->getMessage()
            );
            $this->message = 'Internal error while making auxiliary decision';
            return FALSE;
        }
    }

    // react_to_reserve expects the following inputs:
    //
    //   $action:
    //       One of {'add', 'decline'}.
    //
    //   $dieIdx:
    //       (i)  If this is an 'add' action, then this is the die index of the
    //            die to be added.
    //       (ii) If this is a 'decline' action, then this will be ignored.
    //
    // The function returns a boolean telling whether the reaction has been
    // successful.
    // If it fails, $this->message will say why it has failed.

    public function react_to_reserve(
        $playerId,
        $gameId,
        $action,
        $dieIdx = NULL
    ) {
        try {
            $game = $this->load_game($gameId);
            if (!$this->is_action_current(
                $game,
                BMGameState::CHOOSE_RESERVE_DICE,
                'ignore',
                'ignore',
                $playerId
            )) {
                return FALSE;
            }

            $playerIdx = array_search($playerId, $game->playerIdArray);

            switch ($action) {
                case 'add':
                    if (!array_key_exists($dieIdx, $game->activeDieArrayArray[$playerIdx]) ||
                        !$game->activeDieArrayArray[$playerIdx][$dieIdx]->has_skill('Reserve')) {
                        $this->message = 'Invalid reserve choice';
                        return FALSE;
                    }
                    $die = $game->activeDieArrayArray[$playerIdx][$dieIdx];
                    $die->selected = TRUE;
                    $waitingOnActionArray = $game->waitingOnActionArray;
                    $waitingOnActionArray[$playerIdx] = FALSE;
                    $game->waitingOnActionArray = $waitingOnActionArray;
                    $game->log_action(
                        'add_reserve',
                        $game->playerIdArray[$playerIdx],
                        array( 'die' => $die->get_action_log_data(), )
                    );
                    $this->message = 'Reserve die chosen successfully';
                    break;
                case 'decline':
                    $waitingOnActionArray = $game->waitingOnActionArray;
                    $waitingOnActionArray[$playerIdx] = FALSE;
                    $game->waitingOnActionArray = $waitingOnActionArray;
                    $game->log_action(
                        'decline_reserve',
                        $game->playerIdArray[$playerIdx],
                        array('declineReserve' => TRUE)
                    );
                    $this->message = 'Declined reserve dice';
                    break;
                default:
                    $this->message = 'Invalid response to reserve choice.';
                    return FALSE;
            }

            $this->save_game($game);


            return TRUE;
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::react_to_reserve: " .
                $e->getMessage()
            );
            $this->message = 'Internal error while making reserve decision';
            return FALSE;
        }
    }

    // react_to_initiative expects the following inputs:
    //
    //   $action:
    //       One of {'chance', 'focus', 'decline'}.
    //
    //   $dieIdxArray:
    //       (i)   If this is a 'chance' action, then an array containing the
    //             index of the chance die that is being rerolled.
    //       (ii)  If this is a 'focus' action, then this is the nonempty array
    //             of die indices corresponding to the die values in
    //             dieValueArray. This can be either the indices of ALL focus
    //             dice OR just a subset.
    //       (iii) If this is a 'decline' action, then this will be ignored.
    //
    //   $dieValueArray:
    //       This is only used for the 'focus' action. It is a nonempty array
    //       containing the values of the focus dice that have been chosen by
    //       the user. The die indices of the dice being specified are given in
    //       $dieIdxArray.
    //
    // The function returns a boolean telling whether the reaction has been
    // successful.
    // If it fails, $this->message will say why it has failed.

    public function react_to_initiative(
        $playerId,
        $gameId,
        $roundNumber,
        $submitTimestamp,
        $action,
        $dieIdxArray = NULL,
        $dieValueArray = NULL
    ) {
        try {
            $game = $this->load_game($gameId);
            if (!$this->is_action_current(
                $game,
                BMGameState::REACT_TO_INITIATIVE,
                $submitTimestamp,
                $roundNumber,
                $playerId
            )) {
                return FALSE;
            }

            $playerIdx = array_search($playerId, $game->playerIdArray);

            $argArray = array('action' => $action,
                              'playerIdx' => $playerIdx);

            switch ($action) {
                case 'chance':
                    if (1 != count($dieIdxArray)) {
                        $this->message = 'Only one chance die can be rerolled';
                        return FALSE;
                    }
                    $argArray['rerolledDieIdx'] = (int)$dieIdxArray[0];
                    break;
                case 'focus':
                    if (count($dieIdxArray) != count($dieValueArray)) {
                        $this->message = 'Mismatch in number of indices and values';
                        return FALSE;
                    }
                    $argArray['focusValueArray'] = array();
                    foreach ($dieIdxArray as $tempIdx => $dieIdx) {
                        $argArray['focusValueArray'][$dieIdx] = $dieValueArray[$tempIdx];
                    }
                    break;
                case 'decline':
                    $argArray['dieIdxArray'] = $dieIdxArray;
                    $argArray['dieValueArray'] = $dieValueArray;
                    break;
                default:
                    $this->message = 'Invalid action to respond to initiative.';
                    return FALSE;
            }

            $isSuccessful = $game->react_to_initiative($argArray);
            if ($isSuccessful) {
                $this->save_game($game);
                $this->message = 'Successfully gained initiative';
            } else {
                $this->message = $game->message;
            }

            return $isSuccessful;
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::react_to_initiative: " .
                $e->getMessage()
            );
            $this->message = 'Internal error while reacting to initiative';
            return FALSE;
        }
    }

    protected function get_config($conf_key) {
        try {
            $query = 'SELECT conf_value FROM config WHERE conf_key = :conf_key';
            $statement = self::$conn->prepare($query);
            $statement->execute(array(':conf_key' => $conf_key));
            $fetchResult = $statement->fetchAll();

            if (count($fetchResult) != 1) {
                error_log("Wrong number of config values with key " . $conf_key);
                return NULL;
            }
            return $fetchResult[0]['conf_value'];
        } catch (Exception $e) {
            error_log(
                "Caught exception in BMInterface::get_config: " .
                $e->getMessage()
            );
            return NULL;
        }
    }

    public function __get($property) {
        if (property_exists($this, $property)) {
            switch ($property) {
                default:
                    return $this->$property;
            }
        }
    }

    public function __set($property, $value) {
        switch ($property) {
            case 'message':
                throw new LogicException(
                    'message can only be read, not written.'
                );
            default:
                $this->$property = $value;
        }
    }
}
