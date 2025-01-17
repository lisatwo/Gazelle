<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class ForumTest extends TestCase {
    protected Gazelle\ForumCategory $category;
    protected Gazelle\Forum         $forum;
    protected array                 $userList;

    public function setUp(): void {
        $this->userList = [
            'admin' => Helper::makeUser('admin.' . randomString(10), 'forum'),
            'user'  => Helper::makeUser('user.' . randomString(10), 'forum'),
        ];
        $this->userList['admin']->setUpdate('PermissionID', SYSOP)->modify();
    }

    public function tearDown(): void {
        $this->forum->remove();
        $this->category->remove();
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testForum(): void {
        // Forum Categories
        $fcatMan = new \Gazelle\Manager\ForumCategory;
        $initial = count($fcatMan->forumCategoryList()); // from phinx seeds

        // If you hit a duplicate key error here it is due to an aborted previous test run
        $this->category = $fcatMan->create('phpunit category', 10001);
        $this->assertInstanceOf(\Gazelle\ForumCategory::class, $this->category, 'forum-cat-is-forum-cat');

        $categoryEphemeral = $fcatMan->create('phpunit other', 10002);
        $this->assertCount($initial + 2, $fcatMan->forumCategoryList(), 'forum-cat-category-list');
        $this->assertCount($initial + 2, $fcatMan->usageList(), 'forum-cat-usage-list');

        $find = $fcatMan->findById($this->category->id());
        $find->setUpdate('Name', 'phpunit renamed')->modify();
        $this->assertEquals($this->category->id(), $find->id(), 'forum-cat-find');
        $this->assertEquals('phpunit renamed', $find->name(), 'forum-cat-name');
        $this->assertEquals(10001, $find->sequence(), 'forum-cat-sequence');
        $this->assertEquals(1, $categoryEphemeral->remove(), 'forum-cat-remove-unused');
        $this->assertCount($initial + 1, $fcatMan->forumCategoryList(), 'forum-cat-category-removed');
        $this->assertCount($initial + 1, $fcatMan->usageList(), 'forum-cat-usage-removed');

        // Forums
        $forumMan     = new \Gazelle\Manager\Forum;
        $initial      = count($forumMan->nameList());
        $tocTotal     = count($forumMan->tableOfContentsMain());
        $admin        = $this->userList['admin'];
        $user         = $this->userList['user'];
        $userTocTotal = count($forumMan->tableOfContents($user));
        $this->forum  = $forumMan->create(
            user:           $admin,
            sequence:       150,
            categoryId:     $this->category->id(),
            name:           'First forum',
            description:    'This is where it happens',
            minClassRead:   100,
            minClassWrite:  200,
            minClassCreate: 300,
            autoLock:       false,
            autoLockWeeks:  42,
        );
        $this->assertInstanceOf(\Gazelle\Forum::class, $this->forum, 'forum-is-forum');
        $this->assertEquals(0, $this->category->remove(), 'forum-cat-remove-in-use');
        $this->assertCount($tocTotal + 1, $forumMan->tableOfContentsMain(), 'forum-test-toc-main');

        $this->assertFalse($this->forum->autoLock(), 'forum-autolock');
        $this->assertFalse($this->forum->hasRevealVotes(), 'forum-has-reveal-votes');
        $this->assertFalse($this->forum->isLocked(), 'forum-is-locked');
        $this->assertFalse($this->forum->isSticky(), 'forum-is-sticky');
        $this->assertEquals(0, $this->forum->lastPostTime(), 'forum-last-post-time');
        $this->assertEquals(0, $this->forum->numPosts(), 'forum-post-total');
        $this->assertEquals(0, $this->forum->numThreads(), 'forum-thread-total');
        $this->assertEquals($admin->id(), $this->forum->lastAuthorId(), 'forum-last-author-id');
        $this->assertEquals(42, $this->forum->autoLockWeeks(), 'forum-autolock-weeks');
        $this->assertEquals(150, $this->forum->sequence(), 'forum-sequence');
        $this->assertEquals(100, $this->forum->minClassRead(), 'forum-min-class-read');
        $this->assertEquals(200, $this->forum->minClassWrite(), 'forum-min-class-write');
        $this->assertEquals(300, $this->forum->minClassCreate(), 'forum-min-class-create');
        $this->assertEquals($this->category->name(), $this->forum->categoryName(), 'forum-category-name');
        $this->assertEquals('This is where it happens', $this->forum->description(), 'forum-description');
        $this->assertEquals('First forum', $this->forum->name(), 'forum-name');
        $this->assertNull($this->forum->lastThread(), 'forum-last-thread');

        $find = $forumMan->findById($this->forum->id());
        $this->assertEquals($this->forum->id(), $find->id(), 'forum-forum-find');

        $second = $forumMan->create(
            user:           $this->userList['admin'],
            sequence:       100,
            categoryId:     $this->category->id(),
            name:           'Announcements',
            description:    'This is where it begins',
            minClassRead:   200,
            minClassWrite:  300,
            minClassCreate: 700,
            autoLock:       false,
            autoLockWeeks:  52,
        );

        $nameList = $forumMan->nameList();
        $this->assertCount($initial + 2, $nameList, 'forum-name-list');
        $this->assertEquals('First forum', $nameList[$this->forum->id()]['Name'], 'forum-name-list-name-0');
        $this->assertEquals($second->id(), $nameList[$second->id()]['id'], 'forum-name-list-id-1');

        $idList = $forumMan->forumList();
        $this->assertCount($initial + 2, $idList, 'forum-id-list-count');
        $this->assertTrue(in_array($second->id(), $idList), 'forum-id-list-sequence');
        $this->assertCount($userTocTotal + 1, $forumMan->tableOfContents($user), 'forum-test-toc-user');
        $this->assertEquals(0, $forumMan->subscribedForumTotal($user), 'forum-subscribed-total-user');
        $this->assertEquals(1, $second->remove(), 'forum-remove-forum');

        // Forum Threads
        $threadMan = new \Gazelle\Manager\ForumThread;
        $thread    = $threadMan->create($this->forum, $admin->id(), 'thread title', 'this is a new thread');
        $this->assertEquals(1, $thread->postTotal(), 'fthread-post-total');
        $this->assertEquals(0, $thread->lastPage(), 'fthread-last-page');
        $this->assertEquals(0, $thread->lastCatalog(), 'fthread-last-catalog');
        // weird cache shit
        // $this->assertEquals(1, $this->forum->numThreads(), 'fthread-admin-number-thread-total');

        $this->assertEquals($admin->id(), $thread->authorId(), 'fthread-author-id');
        $this->assertEquals($admin->username(), $thread->author()->username(), 'fthread-author-username');
        $this->assertEquals($this->forum->id(), $thread->forumId(), 'fthread-forum-id');
        $this->assertEquals($this->forum->name(), $thread->forum()->name(), 'fthread-forum-title');
        $this->assertEquals($admin->id(), $thread->lastAuthorId(), 'fthread-forum-title');
        $this->assertEquals('thread title', $thread->title(), 'thread-title');
        $this->assertEquals(0, $thread->pinnedPostId(), 'fthread-pinned-post-id');

        $this->assertFalse($thread->hasPoll(), 'fthread-has-poll-no');
        $this->assertFalse($thread->isLocked(), 'fthread-is-locked');
        $this->assertFalse($thread->isPinned(), 'fthread-is-pinned');

        // Forum Thread Notes
        $threadNote = 'this is a note';
        $id = $thread->addThreadNote($admin->id(), $threadNote);
        $this->assertGreaterThan(0, $id, 'fthread-add-thread-note');

        $notes = $thread->threadNotes();
        $this->assertCount(1, $notes, 'fthread-thread-notes');
        $this->assertEquals($threadNote, $notes[0]['Body'], 'fthread-thread-note-body');

        // Forum ACLs
        $secretLevel = $admin->effectiveClass();
        $secret = $forumMan->create(
            user:           $admin,
            sequence:       200,
            categoryId:     $this->forum->categoryId(),
            name:           'Chit-chat',
            description:    'This is where mods chat',
            minClassRead:   $secretLevel,
            minClassWrite:  $secretLevel,
            minClassCreate: $secretLevel,
            autoLock:       false,
            autoLockWeeks:  52,
        );

        $user = $this->userList['user'];
        $this->assertFalse($user->readAccess($secret), 'fthread-secret-user-read');
        $this->assertFalse($user->writeAccess($secret), 'fthread-secret-user-write');
        $this->assertFalse($user->createAccess($secret), 'fthread-secret-user-create');

        $this->assertTrue($admin->readAccess($secret), 'fthread-secret-admin-read');
        $this->assertTrue($admin->writeAccess($secret), 'fthread-secret-admin-write');
        $this->assertTrue($admin->createAccess($secret), 'fthread-secret-admin-create');

        /* Need to extend modify() to joined tables
        $user->setUpdate('PermittedForums', $secret->id())->modify();
        $this->assertTrue($user->readAccess($secret), 'fthread-secret-user-permitted-read');
        $this->assertTrue($user->writeAccess($secret), 'fthread-secret-user-permitted-write');
        $this->assertTrue($user->createAccess($secret), 'fthread-secret-user-permitted-create');
        */
        $this->assertEquals(1, $secret->remove(), 'forum-secret-remove');

        // Forum Posts
        $userSub = new \Gazelle\User\Subscription($user);
        $this->assertFalse($userSub->isSubscribed($thread->id()), 'fpost-user-is-not-subbed');

        $userSub->subscribe($thread->id());
        $this->assertTrue($userSub->isSubscribed($thread->id()), 'fpost-user-is-now-subbed');
        $list = $userSub->subscriptionList();
        $this->assertCount(1, $list, 'fpost-subscriptions-list');
        $this->assertEquals($thread->id(), $list[0], 'fpost-subscriptions-first');

        $admin = $this->userList['admin'];
        $this->assertEquals(1, $admin->stats()->forumPostTotal(), 'fpost-first-user-stats');
        $message = 'first reply';
        $postId = $thread->addPost($admin->id(), $message);
        $this->assertEquals(2, $admin->stats()->flush()->forumPostTotal(), 'fpost-first-user-reply');

        /* post first reply */
        $postMan = new \Gazelle\Manager\ForumPost;
        $post    = $postMan->findById($postId);
        $this->assertEquals($message, $post->body(), 'fpost-first-post');
        $this->assertEquals(1, $this->forum->numPosts(), 'fpost-forum-post-total');

        $this->assertEquals(1, $userSub->unread(), 'fpost-subscriptions-user-unread');
        $adminSub = new \Gazelle\User\Subscription($admin); // now sub them
        $this->assertEquals(0, $adminSub->unread(), 'fpost-subscriptions-admin-unread');
        $adminSub->subscribe($thread->id());

        /* quote first post in reply */
        $body = "good job @{$admin->username()}";
        $replyId = $thread->addPost($user->id(), $body);
        // Should the following actions (quote and subscription handling) be performed by the addPost() method?
        (new Gazelle\User\Notification\Quote($admin))->create(new Gazelle\Manager\User, $body, $replyId, 'forums', $thread->id());
        (new Gazelle\Manager\Subscription)->flushPage('forums', $thread->id());

        $this->assertEquals(1, $forumMan->unreadSubscribedForumTotal($admin), 'fpost-subscriptions-admin-forum-man-unread');
        $this->assertEquals(1, $adminSub->flush()->unread(), 'fpost-subscriptions-admin-new-unread');

        $quote = new Gazelle\User\Quote($admin);
        $this->assertEquals(1, $quote->total(), 'fpost-quote-admin-total');
        $this->assertEquals(1, $quote->unreadTotal(), 'fpost-quote-admin-unread-total');

        $page = $quote->page(10, 0);
        $this->assertCount(1, $page, 'fpost-quote-page-count');
        $this->assertEquals($admin->id(), $page[0]['quoter_id'], 'fpost-quote-page-0-quoter');
        $this->assertEquals($postMan->findById($replyId)->url(), $page[0]['jump'], 'fpost-quote-page-0-jump');

        $this->assertEquals(1, $quote->clearThread($thread->id(), $postId, $replyId), 'fpost-clear-thread');
        $this->assertEquals(0, $quote->total(), 'fpost-quote-admin-total-clear');
        $this->assertEquals(0, $quote->unreadTotal(), 'fpost-quote-admin-unread-total-clear');

        $latest = $adminSub->latestSubscriptionList(true, 10, 0);
        $this->assertCount(1, $latest, 'fpost-subscription-latest-total');
        $this->assertEquals($thread->id(), $latest[0]['PageID'], 'fpost-quote-admin-unread-page-id');
        $this->assertNull($latest[0]['PostID'], 'fpost-quote-admin-unread-post-id');

        $thread->catchup($admin->id(), $replyId);
        $this->assertEquals(1, $adminSub->unread(), 'fpost-subscriptions-admin-one-read');

        $readLast = $this->forum->userLastRead($admin->id(), 50);
        $this->assertCount(1, $readLast, 'forum-last-read-list-total');
        $this->assertEquals(
            [
                $thread->id() => [
                    "TopicID" => $thread->id(),
                    "PostID"  => $replyId,
                    "Page"    => 1,
                ]
            ],
            $readLast,
            'forum-last-read-list-one'
        );

        // $this->assertEquals(3, $this->forum->userCatchup($admin->id()), 'forum-user-catchup');

        // Forum Polls
        $answer  = ['apple', 'banana', 'carrot'];
        $pollMan = new \Gazelle\Manager\ForumPoll;
        $poll    = $pollMan->create($thread->id(), 'Best food', $answer);
        $this->assertInstanceOf(\Gazelle\ForumPoll::class, $poll, 'forum-poll-is-forum-poll');
        $this->assertFalse($poll->isClosed(), 'forum-poll-is-not-closed');
        $this->assertFalse($poll->hasRevealVotes(), 'forum-poll-is-not-featured');
        $this->assertFalse($poll->isFeatured(), 'forum-poll-is-not-featured');
        $this->assertEquals(0, $poll->total(), 'forum-poll-total');
        $this->assertEquals($thread->id(), $poll->thread()->id(), 'forum-poll-thread-id');
        $this->assertCount(3, $poll->vote(), 'forum-poll-vote-count');
        $this->assertEquals($answer[1], $poll->vote()[1]['answer'], 'forum-poll-vote-1');

        $find = $pollMan->findById($poll->id());
        $this->assertEquals($poll->id(), $find->id(), 'forum-poll-find-by-id');

        $this->assertEquals(1, $poll->addAnswer('sushi'), 'forum-poll-add-answer');

        $this->assertEquals(0, $poll->addVote($user->id(), 12345), 'forum-poll-bad-vote');
        $this->assertEquals(1, $poll->addVote($user->id(), 2),     'forum-poll-good-vote');
        $this->assertEquals(2, $poll->response($user->id()),       'forum-poll-response-before');
        $this->assertEquals(0, $poll->addVote($user->id(), 3),     'forum-poll-revote');
        $this->assertEquals(1, $poll->modifyVote($user->id(), 3),  'forum-poll-change-vote');
        $this->assertEquals(3, $poll->response($user->id()),       'forum-poll-response-after');

        $this->assertEquals(1, $poll->moderate(true, false), 'forum-poll-moderate-feature-open');
        $this->assertTrue($poll->isFeatured(), 'forum-poll-is-featured');

        $this->assertEquals(1, $poll->close(), 'forum-poll-close');
        $this->assertTrue($poll->isClosed(), 'forum-poll-is-closed');
        $this->assertFalse($poll->isFeatured(), 'forum-poll-is-no-longer-featured');
    }
}
