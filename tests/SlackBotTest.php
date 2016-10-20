<?php

use Frlnc\Slack\Core\Commander;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Http\SlackResponseFactory;
use Mockery as m;

use Mockery\MockInterface;
use Mpociot\SlackBot\Answer;
use Mpociot\SlackBot\Button;
use Mpociot\SlackBot\Question;
use Mpociot\SlackBot\SlackBot;
use SuperClosure\Serializer;
use Symfony\Component\HttpFoundation\ParameterBag;

class SlackBotTest extends Orchestra\Testbench\TestCase
{

    /** @var  MockInterface */
    protected $commander;

    public function tearDown()
    {
        m::close();
    }

    protected function getBot($responseData)
    {
        $interactor = new CurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);
        $request = m::mock(\Illuminate\Http\Request::class.'[json]');
        $request->shouldReceive('json')->once()->andReturn(new ParameterBag($responseData));
        $this->commander = m::mock(Commander::class);
        return new SlackBot(new Serializer(), $this->commander, $request);
    }

    protected function getBotWithInteractiveData($payload)
    {
        $interactor = new CurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);
        /** @var \Illuminate\Http\Request $request */
        $request = new \Illuminate\Http\Request();
        $request->replace([
            'payload' => $payload
        ]);
        $this->commander = m::mock(Commander::class);
        return new SlackBot(new Serializer(), $this->commander, $request);
    }

    /** @test */
    public function it_does_not_hear_commands()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'bar'
            ]
        ]);

        $slackbot->hears('foo', function ($bot) use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
    }

    /** @test */
    public function it_hears_matching_commands()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'foo'
            ]
        ]);

        $slackbot->hears('foo', function ($bot) use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    /** @test */
    public function it_passes_itself_to_the_closure()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'foo'
            ]
        ]);

        $slackbot->hears('foo', function ($bot) use (&$called) {
            $called = true;
            $this->assertInstanceOf(SlackBot::class, $bot);
        });
        $this->assertTrue($called);
    }

    /** @test */
    public function it_allows_regular_expressions()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'Hi Julia'
            ]
        ]);

        $slackbot->hears('hi {name}', function ($bot, $name) use (&$called) {
            $called = true;
            $this->assertSame('Julia', $name);
        });
        $this->assertTrue($called);
    }

    /** @test */
    public function it_returns_regular_expression_matches()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'I am Gandalf the grey'
            ]
        ]);

        $slackbot->hears('I am {name} the {attribute}', function ($bot, $name, $attribute) use (&$called) {
            $called = true;
            $this->assertSame('Gandalf', $name);
            $this->assertSame('grey', $attribute);
        });
        $this->assertTrue($called);
    }

    /** @test */
    public function it_returns_the_matches()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'I am Gandalf'
            ]
        ]);

        $slackbot->hears('I am {name}', function ($bot, $name) use (&$called) {
            $called = true;
        });
        $matches = $slackbot->getMatches();
        $this->assertSame('Gandalf', $matches['name']);
        $this->assertTrue($called);
    }

    /** @test */
    public function it_returns_the_message()
    {
        $slackbot = $this->getBot([
            'event' => [
                'text' => 'Hi Julia'
            ]
        ]);
        $this->assertSame('Hi Julia', $slackbot->getMessage());
    }

    /** @test */
    public function it_does_not_return_messages_for_bots()
    {
        $slackbot = $this->getBot([
            'event' => [
                'bot_id' => 'foo',
                'text' => 'Hi Julia'
            ]
        ]);
        $this->assertSame('', $slackbot->getMessage());
    }

    /** @test */
    public function it_detects_bots()
    {
        $slackbot = $this->getBot([
            'event' => [
                'text' => 'Hi Julia'
            ]
        ]);
        $this->assertFalse($slackbot->isBot());

        $slackbot = $this->getBot([
            'event' => [
                'bot_id' => 'foo',
                'text' => 'Hi Julia'
            ]
        ]);
        $this->assertTrue($slackbot->isBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $slackbot = $this->getBot([
            'event' => [
                'user' => 'U0X12345'
            ]
        ]);
        $this->assertSame('U0X12345', $slackbot->getUser());
    }

    /** @test */
    public function it_returns_the_channel_id()
    {
        $slackbot = $this->getBot([
            'event' => [
                'channel' => 'general'
            ]
        ]);
        $this->assertSame('general', $slackbot->getChannel());
    }

    /** @test */
    public function it_returns_the_slack_token()
    {
        $slackbot = $this->getBot([
            'event' => [
                'user' => 'U0X12345'
            ]
        ]);
        $this->commander->shouldReceive('setToken')->once()->with('TOKEN');
        $slackbot->initialize('TOKEN');
        $this->assertSame('TOKEN', $slackbot->getToken());
    }

    /** @test */
    public function it_responds_back_to_the_channel_message()
    {
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'channel' => 'general'
            ]
        ]);
        $this->commander
            ->shouldReceive('execute')
            ->once()
            ->with('chat.postMessage', [
                'token' => 'foo',
                'channel' => 'general',
                'text' => 'This is my response'
            ]);

        $slackbot->respond('This is my response');
    }

    /** @test */
    public function it_responds_to_custom_channels()
    {
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'channel' => 'general'
            ]
        ]);
        $this->commander
            ->shouldReceive('execute')
            ->once()
            ->with('chat.postMessage', [
                'token' => 'foo',
                'channel' => 'customchannel',
                'text' => 'This is my response'
            ]);

        $slackbot->respond('This is my response', 'customchannel');
    }

    /** @test */
    public function it_can_send_questions()
    {
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'channel' => 'general'
            ]
        ]);
        $question = Question::create('How are you doing?')
                ->addButton(Button::create('Great'))
                ->addButton(Button::create('Good'));

        $this->commander
            ->shouldReceive('execute')
            ->once()
            ->with('chat.postMessage', [
                'token' => 'foo',
                'channel' => 'customchannel',
                'text' => '',
                'attachments' => json_encode($question)
            ]);

        $slackbot->respond($question, 'customchannel');
    }

    /** @test */
    public function it_can_store_conversations()
    {
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'user' => 'UX12345',
                'channel' => 'general'
            ]
        ]);

        $conversation = new TestConversation();
        $slackbot->storeConversation($conversation, function($answer){});

        $this->assertTrue(Cache::has('conversation:UX12345-general'));

        $cached = Cache::get('conversation:UX12345-general');

        $this->assertSame($conversation, $cached['conversation']);

        $this->assertTrue(is_string($cached['next']));
    }

    /** @test */
    public function it_can_start_conversations()
    {
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'user' => 'UX12345',
                'channel' => 'general'
            ]
        ]);

        $conversation = m::mock(TestConversation::class);
        $conversation->shouldReceive('setBot')
            ->once()
            ->with($slackbot);

        $conversation->shouldReceive('run')
            ->once();

        $slackbot->startConversation($conversation);
    }

    /** @test */
    public function it_picks_up_conversations()
    {
        $GLOBALS['answer'] = '';
        $GLOBALS['called'] = false;
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'user' => 'UX12345',
                'channel' => 'general',
                'text' => 'Hi Julia'
            ]
        ]);

        $conversation = new BotTestConversation();

        $slackbot->storeConversation($conversation, function($answer) use (&$called) {
            $GLOBALS['answer'] = $answer;
            $GLOBALS['called'] = true;
        });

        /**
         * Now that the first message is saved, fake a reply
         */
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'user' => 'UX12345',
                'channel' => 'general',
                'text' => 'Hello again'
            ]
        ]);
        $this->commander->shouldReceive('setToken');
        $slackbot->initialize('TOKEN');

        $this->assertInstanceOf(Answer::class, $GLOBALS['answer']);
        $this->assertFalse($GLOBALS['answer']->isInteractiveMessageReply());
        $this->assertSame('Hello again', $GLOBALS['answer']->getText());
        $this->assertTrue($GLOBALS['called']);
    }

    /** @test */
    public function it_detects_users_from_interactive_messages()
    {
        $slackbot = $this->getBotWithInteractiveData(file_get_contents(__DIR__ . '/fixtures/payload.json'));
        $this->assertSame('U045VRZFT', $slackbot->getUser());
    }

    /** @test */
    public function it_detects_bots_from_interactive_messages()
    {
        $slackbot = $this->getBotWithInteractiveData(file_get_contents(__DIR__ . '/fixtures/payload.json'));
        $this->assertFalse($slackbot->isBot());
    }

    /** @test */
    public function it_detects_channels_from_interactive_messages()
    {
        $slackbot = $this->getBotWithInteractiveData(file_get_contents(__DIR__ . '/fixtures/payload.json'));
        $this->assertSame('C065W1189', $slackbot->getChannel());
    }

    /** @test */
    public function it_responds_back_to_the_channel_message_from_interactive_messages()
    {
        $slackbot = $this->getBotWithInteractiveData(file_get_contents(__DIR__ . '/fixtures/payload.json'));
        $this->commander
            ->shouldReceive('execute')
            ->once()
            ->with('chat.postMessage', [
                'token' => 'xAB3yVzGS4BQ3O9FACTa8Ho4',
                'channel' => 'C065W1189',
                'text' => 'This is my response'
            ]);

        $slackbot->respond('This is my response');
    }
}

class BotTestConversation extends \Mpociot\SlackBot\Conversation {
    public function run(){}
}