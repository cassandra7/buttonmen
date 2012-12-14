<?php

require_once "engine/BMGame.php";

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2012-12-11 at 13:27:50.
 */
class BMGameTest extends PHPUnit_Framework_TestCase {

    /**
     * @var BMGame
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->object = new BMGame;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {

    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_start_game() {
        $this->object->gameState = BMGameState::startGame;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::startGame, $this->object->gameState);

        // missing the playerIdxArray
        $this->object->gameState = BMGameState::startGame;
        if (isset($this->object->playerIdxArray)) {
            unset($this->object->playerIdxArray);
        }
        $Button1 = new BMButton;
        $Button2 = new BMButton;
        $this->object->buttonArray = array($Button1, $Button2);
        $this->object->maxWins = 3;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::startGame, $this->object->gameState);

        $this->object->gameState = BMGameState::startGame;
        $this->object->playerIdxArray = array(12345, 54321);
        $Button1 = new BMButton;
        $Button2 = new BMButton;
        $this->object->buttonArray = array($Button1, $Button2);
        $this->object->maxWins = 3;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::applyHandicaps, $this->object->gameState);
        $this->assertEquals(array(FALSE, FALSE), $this->object->passStatusArray);
        $this->assertEquals(array(array(0, 0, 0), array(0, 0, 0)),
                            $this->object->gameScoreArray);
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_apply_handicaps() {
        $this->object->playerIdxArray = array(12345, 54321);
        $this->object->gameState = BMGameState::applyHandicaps;
        $this->object->maxWins = 3;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::applyHandicaps,
                            $this->object->gameState);

        $this->object->playerIdxArray = array('12345', '54321');
        $this->object->gameState = BMGameState::applyHandicaps;
        $this->object->gameScoreArray = array(array(0, 0, 0),array(0, 0, 0));
        $this->object->maxWins = 3;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::chooseAuxiliaryDice,
                            $this->object->gameState);

        $this->object->playerIdxArray = array('12345', '54321');
        $this->object->gameState = BMGameState::applyHandicaps;
        $this->object->gameScoreArray = array(array(3, 0, 0),array(0, 3, 0));
        $this->object->maxWins = 3;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::endGame, $this->object->gameState);
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_choose_auxiliary_dice() {
        $this->object->gameState = BMGameState::chooseAuxiliaryDice;
        $button1 = new BMButton;
        $button1->recipe = '(4) (8) (12) (20)';
        if (isset($button1->dieArray)) {
            unset($button1->dieArray);
        }
        $button2 = new BMButton;
        $button2->recipe = '(4) (4) (4) (20)';
        if (isset($button2->dieArray)) {
            unset($button2->dieArray);
        }

        $this->object->buttonArray = array($button1, $button2);
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::loadDice, $this->object->gameState);

        $button3 = new BMButton;
        $button3->recipe = '(4) (4) (8) +(20)';
        if (isset($button3->dieArray)) {
            unset($button3->dieArray);
        }

        $this->object->gameState = BMGameState::chooseAuxiliaryDice;
        $this->object->buttonArray = array($button1, $button3);
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::chooseAuxiliaryDice,
                            $this->object->gameState);
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_load_dice() {
        $this->object->gameState = BMGameState::loadDice;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::loadDice, $this->object->gameState);

        $this->object->gameState = BMGameState::loadDice;
        $die1 = new BMDie;
        $die2 = new BMDie;
        $this->object->activeDieArrayArray = array(array($die1), array($die2));
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::specifyDice, $this->object->gameState);
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_determine_initiative() {
        $this->object->gameState = BMGameState::determineInitiative;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::determineInitiative, $this->object->gameState);

        $this->object->gameState = BMGameState::determineInitiative;
        $this->object->playerWithInitiativeIdx = 0;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::startRound, $this->object->gameState);
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_start_turn() {
        $this->object->gameState = BMGameState::startTurn;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::startTurn, $this->object->gameState);

        $this->object->gameState = BMGameState::startTurn;
        $this->object->attack = array(array(), array(), '');
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::endTurn, $this->object->gameState);
        //james: need to check that the attack has been carried out
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_end_turn() {
        $die1 = new BMDie;
        $die2 = new BMDie;

        // both players still have dice and both have not passed
        $this->object->playerIdxArray = array(12345, 54321);
        $this->object->activePlayerIdx = 0;
        $this->object->activeDieArrayArray = array(array($die1),
                                                   array($die2));
        $this->object->passStatusArray = array(FALSE, FALSE);
        $this->object->gameState = BMGameState::endTurn;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::startTurn, $this->object->gameState);
        $this->assertTrue(isset($this->object->activePlayerIdx));
        $this->assertEquals(1, $this->object->activePlayerIdx);
        $this->assertTrue(isset($this->object->activeDieArrayArray));
        $this->assertEquals(array(FALSE, FALSE), $this->object->passStatusArray);

        $this->object->playerIdxArray = array(12345, 54321);
        $this->object->activePlayerIdx = 1;
        $this->object->activeDieArrayArray = array(array($die1),
                                                   array($die2));
        $this->object->passStatusArray = array(TRUE, FALSE);
        $this->object->gameState = BMGameState::endTurn;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::startTurn, $this->object->gameState);
        $this->assertTrue(isset($this->object->activePlayerIdx));
        $this->assertEquals(0, $this->object->activePlayerIdx);
        $this->assertTrue(isset($this->object->activeDieArrayArray));
        $this->assertEquals(array(TRUE, FALSE), $this->object->passStatusArray);

        $this->object->playerIdxArray = array(12345, 54321);
        $this->object->activePlayerIdx = 0;
        $this->object->activeDieArrayArray = array(array($die1),
                                                   array($die2));
        $this->object->passStatusArray = array(FALSE, TRUE);
        $this->object->gameState = BMGameState::endTurn;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::startTurn, $this->object->gameState);
        $this->assertTrue(isset($this->object->activePlayerIdx));
        $this->assertEquals(1, $this->object->activePlayerIdx);
        $this->assertTrue(isset($this->object->activeDieArrayArray));
        $this->assertEquals(array(FALSE, TRUE), $this->object->passStatusArray);

        // both players have passed
        $this->object->activeDieArrayArray = array(array($die1),
                                                   array($die2));
        $this->object->passStatusArray = array(TRUE, TRUE);
        $this->object->gameState = BMGameState::endTurn;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::endRound, $this->object->gameState);

        // the first player has no dice
        $this->object->activeDieArrayArray = array(array($die1),
                                                   array());
        $this->object->passStatusArray = array(FALSE, FALSE);
        $this->object->gameState = BMGameState::endTurn;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::endRound, $this->object->gameState);

        // the second player has no dice
        $this->object->activeDieArrayArray = array(array(),
                                                   array($die2));
        $this->object->passStatusArray = array(FALSE, FALSE);
        $this->object->gameState = BMGameState::endTurn;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::endRound, $this->object->gameState);
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_end_round() {
        $this->object->playerIdxArray = array(12345, 54321);
        $this->object->activePlayerIdx = 0;
        $die1 = new BMDie;
        $die2 = new BMDie;
        $this->object->activeDieArrayArray = array(array($die1), array($die2));
        $this->object->passStatusArray = array(TRUE, TRUE);
        $this->object->maxWins = 3;
        $this->object->gameScoreArray = array(array(1,2,1),
                                              array(2,1,1));
        $this->object->gameState = BMGameState::endRound;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::loadDice, $this->object->gameState);
        $this->assertFalse(isset($this->object->activePlayerIdx));
        $this->assertFalse(isset($this->object->activeDieArrayArray));
        $this->assertEquals(array(FALSE, FALSE), $this->object->passStatusArray);

        $this->object->maxWins = 5;
        $this->object->gameScoreArray = array(array(5,2,1),
                                              array(2,5,1));
        $this->object->gameState = BMGameState::endRound;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::endGame, $this->object->gameState);
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_end_game() {
        $this->object->gameState = BMGameState::endGame;
        $this->object->update_game_state();
        $this->assertEquals(BMGameState::endGame, $this->object->gameState);
    }

    /**
     * @covers BMGame::update_game_state
     */
    public function test_update_game_state_not_set() {
        try {
            $this->object->update_game_state();
            $this->fail('An undefined game state cannot be updated.');
        }
        catch (LogicException $expected) {
        }
    }

    /**
     * @covers BMGame::does_recipe_have_auxiliary_dice
     */
    public function test_does_recipe_have_auxiliary_dice() {
        $this->assertFalse(BMGame::does_recipe_have_auxiliary_dice('(4) (8) (12) (20)'));

        $this->assertTrue(BMGame::does_recipe_have_auxiliary_dice('(4) (8) (12) +(20)'));
    }

    /**
     * @covers BMGame::is_valid_attack
     */
    public function test_is_valid_attack() {
        $method = new ReflectionMethod('BMGame', 'is_valid_attack');
        $method->setAccessible(TRUE);

        // check when there is no attack set
        $this->assertFalse($method->invoke($this->object));

        // check with a pass attack
        $this->object->attack = array(array(), array(), '');
        $this->assertTrue($method->invoke($this->object));

        // james: need to add test cases for invalid attacks
    }

    /**
     * @covers BMGame::reset_play_state
     */
    public function test_reset_game_state() {
        $method = new ReflectionMethod('BMGame', 'reset_play_state');
        $method->setAccessible(TRUE);

        $this->object->playerIdxArray = array(12345, 54321);
        $this->object->activePlayerIdx = 1;
        $this->object->playerWithInitiativeIdx = 0;

        $die1 = new BMDie;
        $die2 = new BMDie;
        $BMDie3 = new BMDie;
        $BMDie4 = new BMDie;

        $this->object->activeDieArrayArray = array(array($die1), array($die2));
        $this->object->passStatusArray = array(TRUE, TRUE);
        $this->object->capturedDieArrayArray = array(array($BMDie3), array($BMDie4));
        $this->object->roundScoreArray = array(40, -25);

        $method->invoke($this->object);
        $this->assertFalse(isset($this->object->activePlayerIdx));
        $this->assertFalse(isset($this->object->playerWithInitiativeIdx));
        $this->assertFalse(isset($this->object->activeDieArrayArray));
        $this->assertEquals(array(FALSE, FALSE), $this->object->passStatusArray);
        $this->assertEquals(array(array(), array()), $this->object->capturedDieArrayArray);
        $this->assertFalse(isset($this->object->roundScoreArray));
    }

    /**
     * @covers BMGame::change_active_player
     */
    public function test_change_active_player() {
        $method = new ReflectionMethod('BMGame', 'change_active_player');
        $method->setAccessible(TRUE);

        $this->object->playerIdxArray = array(1, 12, 21, 3, 15);
        $this->object->activePlayerIdx = 0;
        $method->invoke($this->object);
        $this->assertEquals(1, $this->object->activePlayerIdx);
        $method->invoke($this->object);
        $this->assertEquals(2, $this->object->activePlayerIdx);
        $method->invoke($this->object);
        $this->assertEquals(3, $this->object->activePlayerIdx);
        $method->invoke($this->object);
        $this->assertEquals(4, $this->object->activePlayerIdx);
        $method->invoke($this->object);
        $this->assertEquals(0, $this->object->activePlayerIdx);
    }

    /**
     * @covers BMGame::__get
     */
    public function test__get() {
        // check that a nonexistent property can be gotten gracefully
        $this->assertEquals(NULL, $this->object->nonsenseVariable);

        $die1 = new BMDie;
        $die2 = new BMDie;
        $this->object->buttonArray = array($die1, $die2);
        $this->assertEquals(array($die1, $die2), $this->object->buttonArray);
    }

    /**
     * @covers BMGame::__set
     */
    public function test__set_game_score_array() {
        $this->object->playerIdxArray = array(12345, 54321);
        $die1 = new BMDie;
        $die2 = new BMDie;
        $this->object->dieArrayArray = array(array($die1), array($die2));
        $this->assertEquals($die1, $this->object->dieArrayArray[0][0]);
        $this->assertEquals($die2, $this->object->dieArrayArray[1][0]);

        $this->object->gameScoreArray = array(array(2,1,1), array(1,2,1));

        try {
            $this->object->gameScoreArray = array(array(2,1,1), array(1,2));
            $this->fail('W/L/D must be three numbers.');
        }
        catch (InvalidArgumentException $expected) {
        }

        try {
            $this->object->gameScoreArray = array(array(2,1,1));
            $this->fail('There must be the same number of players and game scores.');
        }
        catch (InvalidArgumentException $expected) {
        }
    }

    /**
     * @covers BMGame::__set
     */
    public function test__set_attack() {
        try {
            $this->object->attack = array(array(1), array(2));
            $this->fail('There must be exactly three elements in attack.');
        }
        catch (InvalidArgumentException $expected) {
        }

        try {
            $this->object->attack = array(1, array(2), '');
            $this->fail('The first element of attack must be an array.');
        }
        catch (InvalidArgumentException $expected) {
        }

        try {
            $this->object->attack = array(array(1), 2, '');
            $this->fail('The second element of attack must be an array.');
        }
        catch (InvalidArgumentException $expected) {
        }

        // james: add test about third element of attack

        // check that a pass attack is valid
        $this->object->attack = array(array(), array(), '');

        // check that a skill attack is valid
        $this->object->attack = array(array(0, 5), array(2), 'skill');
    }

    /**
     * @covers BMGame::__isset
     */
    public function test__isset() {
        $this->assertFalse(isset($this->object->buttonArray));

        $button1 = new BMButton;
        $button2 = new BMButton;
        $this->object->buttonArray = array($button1, $button2);
        $this->assertTrue(isset($this->object->buttonArray));
    }

    /**
     * @covers BMGame::__unset
     */
    public function test__unset() {
        // check that a nonexistent property can be unset gracefully
        unset($this->object->rubbishVariable);

        $button1 = new BMButton;
        $button2 = new BMButton;
        $this->object->buttonArray = array($button1, $button2);
        unset($this->object->buttonArray);
        $this->assertFalse(isset($this->object->buttonArray));
    }
}


/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2012-12-11 at 13:27:50.
 */
class BMGameStateTest extends PHPUnit_Framework_TestCase {

    /**
     * @var BMGameState
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->object = new BMGameState;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {

    }

    /**
     */
    public function testBMGameStateOrder() {
        $this->assertTrue(BMGameState::startGame <
                          BMGameState::applyHandicaps);
        $this->assertTrue(BMGameState::applyHandicaps <
                          BMGameState::chooseAuxiliaryDice);
        $this->assertTrue(BMGameState::chooseAuxiliaryDice <
                          BMGameState::loadDice);
        $this->assertTrue(BMGameState::loadDice <
                          BMGameState::specifyDice);
        $this->assertTrue(BMGameState::specifyDice <
                          BMGameState::determineInitiative);
        $this->assertTrue(BMGameState::determineInitiative <
                          BMGameState::startRound);
        $this->assertTrue(BMGameState::startRound <
                          BMGameState::startTurn);
        $this->assertTrue(BMGameState::startTurn <
                          BMGameState::endTurn);
        $this->assertTrue(BMGameState::endTurn <
                          BMGameState::endRound);
        $this->assertTrue(BMGameState::endRound <
                          BMGameState::endGame);
    }

}
