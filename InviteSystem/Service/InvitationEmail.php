<?php

namespace XenSoluce\InviteSystem\Service;

use XenSoluce\ChipSystem\Entity\ChipItem;
use XenSoluce\InviteSystem\Entity\CodeInvitation;

class InvitationEmail extends \XF\Service\AbstractService
{
    /**
     * @var bool
     */
    protected $isAdmin;
    protected $email = [];
    protected $replaces = [
        'username',
        'code'
    ];
    protected $subject;
    protected $token = '';
    protected $tokenId = 0;
    protected $type = 5;
    protected $invitation = null;
    /**
     * Approve constructor.
     * @param \XF\App $app
     * @param bool $isAdmin
     */
	public function __construct(\XF\App $app, $isAdmin = false)
	{
		parent::__construct($app);
        $this->isAdmin = $isAdmin;
	}

    /**
     * @param array $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @param $token
     * @param $tokenId
     */
    public function setToken($token = '', $tokenId = 0)
    {
        $this->token = $token;
        $this->tokenId = $tokenId;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @param array $values
     * @return string|string[]|null
     */
    protected function renderMessage(array $values = [])
    {
        $options = \XF::options();
        $message = $options->xs_is_predefined_message_email ;
        foreach ($this->replaces as $replace)
        {
            if(isset($values[$replace]))
            {
                $value = $values[$replace];
                $message = preg_replace('/\{' . $replace . '\}/', $value, $message);
            }
            else
            {
                $message = preg_replace('/\{' . $replace . '\}/', '', $message);
            }

        }

        return $message;
    }

    /**
     * @param $subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @return null
     */
    public function getInvitation()
    {
        return $this->invitation;
    }

    /**
     * @throws \XF\PrintableException
     */
    public function sendEmail()
    {
        $visitor = \XF::visitor();
        if(!empty($this->email))
        {
            if(is_array($this->email))
            {
                $i = 0;
                foreach ($this->email as $email)
                {
                    if($i > 9)
                    {
                        return;
                    }
                    if(filter_var($email, FILTER_VALIDATE_EMAIL))
                    {
                        $invitation = $this->invitation($visitor);
                        $this->sender($invitation, $email);
                        ++$i;
                    }

                }
            }
            else
            {
                $email = $this->email;
                $invitation = $this->invitation($visitor);
                $this->invitation = $invitation;
                $this->sender($invitation, $email);
            }
        }
    }

    /**
     * @param $visitor
     * @return CodeInvitation
     * @throws \XF\PrintableException
     */
    protected function invitation($visitor)
    {
        /** @var CodeInvitation $invitation */
        $invitation = $this->em()->create('XenSoluce\InviteSystem:CodeInvitation');
        $invitation->user_id = $visitor->user_id;
        $invitation->token_id = $this->tokenId;
        $invitation->token = $this->token;
        $invitation->type_code = $this->type;
        $invitation->save();
        return $invitation;
    }

    /**
     * @return bool
     */
    public function validEmail()
    {
        if(!filter_var($this->email, FILTER_VALIDATE_EMAIL))
        {
            return false;
        }

        return true;
    }

    /**
     * @param $invitation
     * @param $email
     * @throws \XF\PrintableException
     */
    public function sender(CodeInvitation $invitation, $email)
    {
        $options = \XF::options();
        $visitor = \XF::visitor();
        $message = $this->renderMessage([
            'username' => $visitor->username,
            'code' => $invitation->code
        ]);
        $name = $options->xs_is_sender_name ?: $visitor->username;
        \XF::app()->mailer()->newMail()
            ->setTo($email)
            ->setFrom($options->xs_is_sender_email_address ?: $options->defaultEmailAddress , $name)
            ->setTemplate('xs_is_invitation_email', [
                'subject'    => $this->subject,
                'message'    => $message,
                'invitation' => $invitation
            ])
            ->send();

        /** @var \XenSoluce\InviteSystem\Entity\InvitationEmail $createEmail */
        $createEmail = $this->em()->create('XenSoluce\InviteSystem:InvitationEmail');
        $createEmail->user_id = $visitor->user_id;
        $createEmail->code = $invitation->code;
        $createEmail->code_id = $invitation->code_id;
        $createEmail->subject = $this->subject;
        $createEmail->message = $message;
        $createEmail->email = $email;
        $createEmail->is_admin = $this->isAdmin;
        $createEmail->save();
    }
}