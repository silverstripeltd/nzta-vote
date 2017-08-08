<?php

class VoteControllerExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'vote',
    ];

    /**
     * Returns a json encoded string containing the number of likes and dislikes
     * for the specified vote object.
     *
     * @return string
     */
    public function vote()
    {
        $request = $this->owner->getRequest();
        $response = $this->owner->getResponse();

        if (!$request->isAjax() || !$request->isPOST()) {
            return $response->setStatusCode(400, 'The request needs to be post AJAX.');
        }

        // a commentID of 0 means the vote is not for a comment
        $commentID = $request->postVar('comment_id');
        $status = $request->postVar('vote');

        // sanitize data
        $commentID = Convert::raw2sql($commentID);
        $status = Convert::raw2sql($status);

        // checks if user has already voted and returns error message if so
        $errMsg = $this->voteByCurrentUser($status, $commentID);

        if ($errMsg) {
            return $response->setStatusCode(400, $errMsg);
        }

        // Get all votes to count the amount of likes and dislikes for this object
        $votes = $this->owner->Votes();
        $filter = ['CommentID' => $commentID];

        $numLikes = $votes->filter(array_merge(
            $filter,
            ['Status' => 'Like']
        ))->count();

        $numDislikes = $votes->filter(array_merge(
            $filter,
            ['Status' => 'Dislike']
        ))->count();

        $response->setStatusCode(200);
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode([
            'status' => $status,
            'numLikes' => $numLikes,
            'numDislikes' => $numDislikes
        ]));

        return $response;
    }

    /**
     * Sends a vote as the current user
     *
     * @param string $status
     * @param integer $commentID
     *
     * @return string|null
     */
    protected function voteByCurrentUser($status, $commentID)
    {
        return $this->voteBy(Member::currentUser(), $status, $commentID);
    }

    /**
     * Creates a Vote object for the currently logged in member, and sets the status to the provided value
     *
     * @param Member $member
     * @param string $status
     * @param integer $commentID
     *
     * @return string|null
     */
    private function voteBy($member = null, $status, $commentID)
    {
        $status = Convert::raw2sql($status);
        $commentID = (int)$commentID;

        // check if there is a logged in member
        if (!$member) {
            return 'Sorry, you need to login to vote.';
        }

        // validate status data
        $availableStatuses = Injector::inst()
            ->get('Vote')
            ->dbObject('Status')
            ->enumValues();

        if (!in_array($status, $availableStatuses)) {
            return 'Sorry, this is an invalid status.';
        }

        // validate comment data
        if ($commentID !== 0) {
            $comment = Comment::get()->byID((int)$commentID);

            if (!$comment) {
                return 'Sorry, this is not a valid comment ID.';
            }
        }

        // check whether the member has already voted
        $vote = $this
            ->owner
            ->Votes()
            ->filter([
                'MemberID' => $member->ID,
                'CommentID' => $commentID
            ])
            ->first();

        // if they haven't create a new vote for this member
        if (!$vote) {
            $vote = new Vote([
                'MemberID' => $member->ID,
                'CommentID' => $commentID
            ]);
            $vote->write();

            $this->owner->Votes()->add($vote);
        }

        $vote->Status = $status;
        $vote->write();

        return null;
    }

    /**
     * Gets the status of the vote - like|dislike
     *
     * @param Member $member
     *
     * @return string|null
     */
    public function VoteStatus($member = null)
    {
        if (!$member) {
            return null;
        }

        $vote = $this
            ->owner
            ->Votes()
            ->filter('MemberID', $member->ID)
            ->first();

        return ($vote) ? $vote->Status : null;
    }

    /**
     * Gets the status of the vote based on the current user
     *
     * @return string|null
     */
    public function VoteStatusByCurrentUser()
    {
        return $this->owner->VoteStatus(Member::currentUser());
    }
}