<?php

/** Alternative responder which doesn't use real databases or
 *  sessions, but rather exists only to send dummy data used for
 *  automated testing of API compliance
 */

class dummy_responder {

    // properties

    // N.B. this class is always used for some type of testing,
    // but, the usage here matches the way responder uses this flag:
    // * False: this instance is being accessed remotely via POST
    // * True:  this instance is being accessed locally by unit tests
    private $isTest;               // whether this invocation is for testing

    // constructor
    // * For live invocation:
    //   * start a session (don't use api_core because dummy_responder has no backend)
    // * For test invocation:
    //   * don't start a session
    public function __construct($isTest = FALSE) {
        $this->isTest = $isTest;

        if (!($this->isTest)) {
            session_start();
        }
    }

    // This function looks at the provided arguments, fakes appropriate
    // data to match the public API, and returns either some game
    // data on success, or NULL on failure.  (Failure will happen if
    // the requested arguments are invalid.)
    protected function get_interface_response($args) {

        if ($args['type'] == 'createUser') {
            $dummy_users = array(
                'tester1' => 1,
                'tester2' => 2,
                'tester3' => 3);
            $username = $args['username'];
            if (array_key_exists($username, $dummy_users)) {
                $userid = $dummy_users[$username];
                return array(NULL, "$username already exists (id=$userid)");
            }
            return array(array('userName' => $username), "User $username created successfully");
        }

	// for verisimilitude, choose a game ID of one greater than
	// the number of "existing" games represented in loadGameData
	// and loadActiveGames
        if ($args['type'] == 'createGame') {
            $gameId = '7';
            return array(array('gameId' => $gameId), "Game $gameId created successfully.");
        }

        // Use the same fake games here which were described in loadGameData
        if ($args['type'] == 'loadActiveGames') {
            $data = array(
                'gameIdArray' => array(),
                'opponentIdArray' => array(),
                'opponentNameArray' => array(),
                'myButtonNameArray' => array(),
                'opponentButtonNameArray' => array(),
                'nWinsArray' => array(),
                'nLossesArray' => array(),
                'nDrawsArray' => array(),
                'nTargetWinsArray' => array(),
                'isAwaitingActionArray' => array(),
                'gameStateArray' => array(),
                'statusArray' => array(),
            );

            // game 1
            $data['gameIdArray'][] = "1";
            $data['opponentIdArray'][] = "2";
            $data['opponentNameArray'][] = "tester2";
            $data['myButtonNameArray'][] = "Avis";
            $data['opponentButtonNameArray'][] = "Avis";
            $data['nWinsArray'][] = "0";
            $data['nLossesArray'][] = "0";
            $data['nDrawsArray'][] = "0";
            $data['nTargetWinsArray'][] = "3";
            $data['isAwaitingActionArray'][] = "1";
            $data['gameStateArray'][] = "24";
            $data['statusArray'][] = "ACTIVE";

            // game 2
            $data['gameIdArray'][] = "2";
            $data['opponentIdArray'][] = "2";
            $data['opponentNameArray'][] = "tester2";
            $data['myButtonNameArray'][] = "Avis";
            $data['opponentButtonNameArray'][] = "Avis";
            $data['nWinsArray'][] = "0";
            $data['nLossesArray'][] = "0";
            $data['nDrawsArray'][] = "0";
            $data['nTargetWinsArray'][] = "3";
            $data['isAwaitingActionArray'][] = "0";
            $data['gameStateArray'][] = "24";
            $data['statusArray'][] = "ACTIVE";

            // game 3
            $data['gameIdArray'][] = "3";
            $data['opponentIdArray'][] = "2";
            $data['opponentNameArray'][] = "tester2";
            $data['myButtonNameArray'][] = "Avis";
            $data['opponentButtonNameArray'][] = "Avis";
            $data['nWinsArray'][] = "0";
            $data['nLossesArray'][] = "0";
            $data['nDrawsArray'][] = "0";
            $data['nTargetWinsArray'][] = "3";
            $data['isAwaitingActionArray'][] = "1";
            $data['gameStateArray'][] = "40";
            $data['statusArray'][] = "ACTIVE";

            // game 4
            $data['gameIdArray'][] = "4";
            $data['opponentIdArray'][] = "2";
            $data['opponentNameArray'][] = "tester2";
            $data['myButtonNameArray'][] = "Avis";
            $data['opponentButtonNameArray'][] = "Avis";
            $data['nWinsArray'][] = "0";
            $data['nLossesArray'][] = "0";
            $data['nDrawsArray'][] = "0";
            $data['nTargetWinsArray'][] = "3";
            $data['isAwaitingActionArray'][] = "0";
            $data['gameStateArray'][] = "40";
            $data['statusArray'][] = "ACTIVE";

            // game 5
            $data['gameIdArray'][] = "5";
            $data['opponentIdArray'][] = "2";
            $data['opponentNameArray'][] = "tester2";
            $data['myButtonNameArray'][] = "Avis";
            $data['opponentButtonNameArray'][] = "Avis";
            $data['nWinsArray'][] = "0";
            $data['nLossesArray'][] = "0";
            $data['nDrawsArray'][] = "0";
            $data['nTargetWinsArray'][] = "3";
            $data['isAwaitingActionArray'][] = "0";
            $data['gameStateArray'][] = "60";
            $data['statusArray'][] = "COMPLETE";

            // game 6
            $data['gameIdArray'][] = "6";
            $data['opponentIdArray'][] = "2";
            $data['opponentNameArray'][] = "tester2";
            $data['myButtonNameArray'][] = "Buck";
            $data['opponentButtonNameArray'][] = "Von Pinn";
            $data['nWinsArray'][] = "0";
            $data['nLossesArray'][] = "0";
            $data['nDrawsArray'][] = "0";
            $data['nTargetWinsArray'][] = "3";
            $data['isAwaitingActionArray'][] = "1";
            $data['gameStateArray'][] = "24";
            $data['statusArray'][] = "ACTIVE";

            return array($data, "All game details retrieved successfully.");
        }

        if ($args['type'] == 'loadButtonNames') {
            $data = array(
              'buttonNameArray' => array(),
              'recipeArray' => array(),
              'hasUnimplementedSkillArray' => array(),
            );

            // a button with no special skills
            $data['buttonNameArray'][] = "Avis";
            $data['recipeArray'][] = "(4) (4) (10) (12) (X)";
            $data['hasUnimplementedSkillArray'][] = false;

            // a button with an unimplemented skill
            $data['buttonNameArray'][] = "Adam Spam";
            $data['recipeArray'][] = "F(4) F(6) (6) (12) (X)";
            $data['hasUnimplementedSkillArray'][] = true;

            // a button with four dice and some implemented skills
            $data['buttonNameArray'][] = "Jellybean";
            $data['recipeArray'][] = "p(20) s(20) (V) (X)";
            $data['hasUnimplementedSkillArray'][] = false;

            // Buck
            $data['buttonNameArray'][] = "Buck";
            $data['recipeArray'][] = "(6,6) (10) (12) (20) (W,W)";
            $data['hasUnimplementedSkillArray'][] = false;

            // Von Pinn
            $data['buttonNameArray'][] = "Von Pinn";
            $data['recipeArray'][] = "(4) p(6,6) (10) (20) (W)";
            $data['hasUnimplementedSkillArray'][] = false;

            return array($data, "All button names retrieved successfully.");
        }

        // The dummy loadGameData returns one of a number of
        // sets of dummy game data, for general test use.
        // Specify which one you want using the game number:
        //   1: a newly-created game, waiting for both players to set swing dice
        //   2: new game in which the active player has set swing dice
        //   3: game in which it is the current player's turn to attack
        //   4: game in which it is the opponent's turn to attack
        //   5: game which has been completed
        if ($args['type'] == 'loadGameData') {
            $data = NULL;
            if ($args['game'] == '1') {
                $data = array(
                    'gameData' => array(
                        "status" => "ok",
                        "data" => array(
                            "gameId" => "1",
                            "gameState" => 24,
                            "roundNumber" => 1,
                            "maxWins" => "3",
                            "activePlayerIdx" => null,
                            "playerWithInitiativeIdx" => null,
                            "playerIdArray" => array("1", "2"),
                            "buttonNameArray" => array("Avis", "Avis"),
                            "waitingOnActionArray" => array(true,true),
                            "nDieArray" => array(5, 5),
                            "valueArrayArray" => array(array(null,null,null,null,null),
                                                       array(null,null,null,null,null)),
                            "sidesArrayArray" => array(array(4,4,10,12,null),
                                                       array(null,null,null,null,null)),
                            "dieRecipeArrayArray" => array(array("(4)","(4)","(10)","(12)","(X)"),
                                                           array("(4)","(4)","(10)","(12)","(X)")),
                            "swingRequestArrayArray" => array(array("X"), array("X")),
                            "validAttackTypeArray" => array(),
                            "roundScoreArray" => array(15, 15),
                            "gameScoreArrayArray" => array(array("W" => 0, "L" => 0, "D" => 0),
                                                           array("W" => 0, "L" => 0, "D" => 0)),
                        ),
                    ),
                    'currentPlayerIdx' => 0,
                    'gameActionLog' => array(),
                );
            } elseif ($args['game'] == '2') {
                $data = array(
                    'gameData' => array(
                        "status" => "ok",
                        "data" => array(
                            "gameId" => "2",
                            "gameState" => 24,
                            "roundNumber" => 1,
                            "maxWins" => "3",
                            "activePlayerIdx" => null,
                            "playerWithInitiativeIdx" => null,
                            "playerIdArray" => array("1", "2"),
                            "buttonNameArray" => array("Avis", "Avis"),
                            "waitingOnActionArray" => array(false,true),
                            "nDieArray" => array(5, 5),
                            "valueArrayArray" => array(array(null,null,null,null,null),
                                                       array(null,null,null,null,null)),
                            "sidesArrayArray" => array(array(4,4,10,12,4),
                                                       array(null,null,null,null,null)),
                            "dieRecipeArrayArray" => array(array("(4)","(4)","(10)","(12)","(X)"),
                                                           array("(4)","(4)","(10)","(12)","(X)")),
                            "swingRequestArrayArray" => array(array("X"), array("X")),
                            "validAttackTypeArray" => array(),
                            "roundScoreArray" => array(15, 15),
                            "gameScoreArrayArray" => array(array("W" => 0, "L" => 0, "D" => 0),
                                                           array("W" => 0, "L" => 0, "D" => 0)),
                        ),
                    ),
                    'currentPlayerIdx' => 0,
                    'gameActionLog' => array(),
                );
            } elseif ($args['game'] == '3') {
                $data = array(
                    'gameData' => array(
                        "status" => "ok",
                        "data" => array(
                            "gameId" => "3",
                            "gameState" => 40,
                            "roundNumber" => 1,
                            "maxWins" => "3",
                            "activePlayerIdx" => 0,
                            "playerWithInitiativeIdx" => 0,
                            "playerIdArray" => array("1", "2"),
                            "buttonNameArray" => array("Avis", "Avis"),
                            "waitingOnActionArray" => array(true, false),
                            "nDieArray" => array(5, 5),
                            "valueArrayArray" => array(array("1", "3", "4", "5", "2"),
                                                       array("3", "4", "7", "9", "4")),
                            "sidesArrayArray" => array(array(4,4,10,12,4),
                                                       array(4,4,10,12,4)),
                            "dieRecipeArrayArray" => array(array("(4)","(4)","(10)","(12)","(X)"),
                                                           array("(4)","(4)","(10)","(12)","(X)")),
                            "swingRequestArrayArray" => array(array("X"), array("X")),
                            "validAttackTypeArray" => array("Power" => "Power", "Skill" => "Skill", ),
                            "roundScoreArray" => array(17, 17),
                            "gameScoreArrayArray" => array(array("W" => 0, "L" => 0, "D" => 0),
                                                           array("W" => 0, "L" => 0, "D" => 0)),
                        ),
                    ),
                    'currentPlayerIdx' => 0,
                    'gameActionLog' => array(),
                );
            } elseif ($args['game'] == '4') {
                $data = array(
                    'gameData' => array(
                        "status" => "ok",
                        "data" => array(
                            "gameId" => "4",
                            "gameState" => 40,
                            "roundNumber" => 1,
                            "maxWins" => "3",
                            "activePlayerIdx" => 1,
                            "playerWithInitiativeIdx" => 1,
                            "playerIdArray" => array("1", "2"),
                            "buttonNameArray" => array("Avis", "Avis"),
                            "waitingOnActionArray" => array(false, true),
                            "nDieArray" => array(5, 5),
                            "valueArrayArray" => array(array("3", "4", "7", "9", "4"),
                                                       array("1", "3", "4", "5", "2")),
                            "sidesArrayArray" => array(array(4,4,10,12,4),
                                                       array(4,4,10,12,4)),
                            "dieRecipeArrayArray" => array(array("(4)","(4)","(10)","(12)","(X)"),
                                                           array("(4)","(4)","(10)","(12)","(X)")),
                            "swingRequestArrayArray" => array(array("X"), array("X")),
                            "validAttackTypeArray" => array("Power" => "Power", "Skill" => "Skill", ),
                            "roundScoreArray" => array(17, 17),
                            "gameScoreArrayArray" => array(array("W" => 0, "L" => 0, "D" => 0),
                                                           array("W" => 0, "L" => 0, "D" => 0)),
                        ),
                    ),
                    'currentPlayerIdx' => 0,
                    'gameActionLog' => array(),
                );
            } elseif ($args['game'] == '5') {
                $data = array(
                    'gameData' => array(
                        "status" => "ok",
                        "data" => array(
                            "gameId" => "5",
                            "gameState" => 60,
                            "roundNumber" => 6,
                            "maxWins" => "3",
                            "activePlayerIdx" => null,
                            "playerWithInitiativeIdx" => 1,
                            "playerIdArray" => array("1", "2"),
                            "buttonNameArray" => array("Avis", "Avis"),
                            "waitingOnActionArray" => array(false, false),
                            "nDieArray" => array(0, 0),
                            "valueArrayArray" => array(array(), array()),
                            "sidesArrayArray" => array(array(), array()),
                            "dieRecipeArrayArray" => array(array(), array()),
                            "swingRequestArrayArray" => array(array(), array()),
                            "validAttackTypeArray" => array(),
                            "roundScoreArray" => array(0, 0),
                            "gameScoreArrayArray" => array(array("W" => 3, "L" => 2, "D" => 0),
                                                           array("W" => 2, "L" => 3, "D" => 0)),
                        ),
                    ),
                    'currentPlayerIdx' => 0,
                    'gameActionLog' => array(
                        array("timestamp" => "2013-12-20 00:52:42",
                              "message" => "End of round: tester1 won round 5 (46 vs 30)"),
                        array("timestamp" => "2013-12-20 00:52:42",
                              "message" => "tester1 performed Power attack using [(X):7] against [(4):2]; Defender (4) was captured; Attacker (X) rerolled 7 => 4"),
                        array("timestamp" => "2013-12-20 00:52:36",
                              "message" => "tester2 passed"),
                        array("timestamp" => "2013-12-20 00:52:33",
                              "message" => "tester1 performed Power attack using [(X):14] against [(10):4]; Defender (10) was captured; Attacker (X) rerolled 14 => 7"),
                        array("timestamp" => "2013-12-20 00:52:29",
                              "message" => "tester2 performed Power attack using [(10):10] against [(4):4]; Defender (4) was captured; Attacker (10) rerolled 10 => 4"),
                    ),
                );
            } else if ($args['game'] == '6') {
                $data = array(
                    'gameData' => array(
                        "status" => "ok",
                        "data" => array(
                            "gameId" => "6",
                            "gameState" => 24,
                            "roundNumber" => 1,
                            "maxWins" => "3",
                            "activePlayerIdx" => null,
                            "playerWithInitiativeIdx" => null,
                            "playerIdArray" => array("1", "2"),
                            "buttonNameArray" => array("Buck", "Von Pinn"),
                            "waitingOnActionArray" => array(true,true),
                            "nDieArray" => array(5, 5),
                            "valueArrayArray" => array(array(null,null,null,null,null),
                                                       array(null,null,null,null,null)),
                            "sidesArrayArray" => array(array(12,10,12,20,null),
                                                       array(null,null,null,null,null)),
                            "dieRecipeArrayArray" => array(array("(6,6)","(10)","(12)","(20)","(W,W)"),
                                                           array("(4)","p(6,6)","(10)","(20)","(W)")),
                            "swingRequestArrayArray" => array(array("W"), array("W")),
                            "validAttackTypeArray" => array(),
                            "roundScoreArray" => array(27, 5),
                            "gameScoreArrayArray" => array(array("W" => 0, "L" => 0, "D" => 0),
                                                           array("W" => 0, "L" => 0, "D" => 0)),
                        ),
                    ),
                    'currentPlayerIdx' => 0,
                    'gameActionLog' => array(),
                );
            }

            if ($data) {
                $data['playerNameArray'] = array('tester1', 'tester2');
                $timestamp = new DateTime();
                $data['timestamp'] = $timestamp->format(DATE_RSS);
                return array($data, "Loaded data for game " . $args['game']);
            }
            return array(NULL, "Game does not exist.");
        }

        if ($args['type'] == 'loadPlayerName') {
            return array(array('userName' => 'tester1'), NULL);
        }

        if ($args['type'] == 'loadPlayerNames') {
            $data = array(
                'nameArray' => array(),
            );

            // three test players exist
            $data['nameArray'][] = 'tester1';
            $data['nameArray'][] = 'tester2';
            $data['nameArray'][] = 'tester3';

            return array($data, "Names retrieved successfully.");
        }

        if ($args['type'] == 'submitSwingValues') {
            return array(True, 'Successfully set swing values');
        }

        if ($args['type'] == 'submitTurn') {
            return array(True, 'Dummy turn submission accepted');
        }

        if ($args['type'] == 'login') {
//            $login_success = login($_POST['username'], $_POST['password']);
//            if ($login_success) {
//                $data = array('userName' => $_POST['username']);
//            } else {
//                $data = NULL;
//            }
            return array(NULL, "function not implemented");
        }

        if ($args['type'] == 'logout') {
//            logout();
//            $data = array('userName' => False);
            return array(NULL, "function not implemented");
        }

        return array(NULL, NULL);
    }

    // Ask get_interface_response() for the dummy response to the
    // request, then construct a response.  Match the logic in
    // responder as closely as possible for convenience.
    // * For live (remote) invocation:
    //   * display the output to the user
    // * For test invocation:
    //   * return the output as a PHP variable
    public function process_request($args) {
        $retval = $this->get_interface_response($args);
        $data = $retval[0];
        $message = $retval[1];

        $output = array(
            'data' => $data,
            'message' => $message,
        );
        if ($data) {
            $output['status'] = 'ok';
        } else {
            $output['status'] = 'failed';
        }

        if ($this->isTest) {
            return $output;
        } else {
            header('Content-Type: application/json');
            echo json_encode($output);
        }
    }
}

// If dummy_responder was called via a POST request (rather than
// by test code), the $_POST variable will be set
if ($_POST) {
    $dummy_responder = new dummy_responder(False);
    $dummy_responder->process_request($_POST);
}
?>