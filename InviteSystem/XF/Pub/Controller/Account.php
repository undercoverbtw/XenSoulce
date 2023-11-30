<?php

namespace XenSoluce\InviteSystem\XF\Pub\Controller;

use XenSoluce\InviteSystem\Entity\Token;
use XenSoluce\InviteSystem\Service\InvitationEmail;

class Account extends XFCP_Account
{
    public function actionInvitation()
    {
        $visitor = \XF::visitor();
        $Ban = $this->finder('XenSoluce\InviteSystem:Banning')
            ->where('user_id', $visitor->user_id)
            ->fetchOne();
        if (!empty($Ban))
        {
            $viewParams = ['Ban' => $Ban];
            return $this->view('XenSoluce\InviteSystem:Invitation', 'xs_is_account_Invitation_ban', $viewParams);
        }

        if(!$visitor->canInvite())
        {
            return $this->noPermission();
        }

        if ($this->isPost())
        {
            $InvitationPerXDay = $this->InvitationPerXDay($visitor);
            if(!$InvitationPerXDay['Between'])
            {
                throw $this->exception(
                    $this->error(\XF::phrase('xs_is_general_code_limit_reached', ['PerMonth' => $InvitationPerXDay['PerMonth']]))
                );
            }
            $token = $this->filter('token', 'int');

            /** @var Token $tokenR */
            $tokenR = $this->em()->find('XenSoluce\InviteSystem:Token', $token);

            if(!$tokenR->canUse())
            {
                return $this->noPermission();
            }
            $sendEmil = $this->filter('send_invite', 'bool');
            if($sendEmil)
            {
                $email = $this->filter('email', 'str');
                if(empty($email))
                {
                    throw $this->exception(
                        $this->error(\XF::phrase('please_enter_value_for_required_field_x', ['field' => 'email']))
                    );
                }
                /** @var InvitationEmail $invitationEmailService */
                $invitationEmailService = $this->service('XenSoluce\InviteSystem:InvitationEmail', true);
                $invitationEmailService->setEmail($this->filter('email', 'str'));

                if(!$invitationEmailService->validEmail())
                {
                    throw $this->exception(
                        $this->error(\XF::phrase('please_enter_valid_email'))
                    );
                }

                $invitationEmailService->setSubject(\XF::options()->xs_is_sender_name ? \XF::options()->xs_is_sender_name : \XF::phrase('xs_is_subject_of_the_email_by_default', [
                    'username' => $visitor->username,
                    'boardTitle' => \XF::options()->boardTitle
                ]));
                $invitationEmailService->setType(1);
                $invitationEmailService->setToken($tokenR->token, $token);
                $invitationEmailService->sendEmail();

                $Code = $invitationEmailService->getInvitation();
            }
            else
            {
                $Code = $this->em()->create('XenSoluce\InviteSystem:CodeInvitation');
                $Code->user_id = $visitor->user_id;
                $Code->token_id = $token;
                $Code->token = $tokenR->token;
                $Code->type_code = 1;
                $Code->save();
            }

            if($tokenR->enable_add_user_group)
            {
                $userGroupCode = $this->em()->create('XenSoluce\InviteSystem:UserGroupCode');
                $userGroupCode->code = $Code->code;
                $userGroupCode->entity_id = $Code->code_id;
                $userGroupCode->max_invite = 1;
                $userGroupCode->type_user_group = $tokenR->type_user_group;
                $userGroupCode->user_group = $tokenR->user_group;
                $userGroupCode->secondary_user_group = $tokenR->secondary_user_group;
                $userGroupCode->save();
            }
            return $this->redirect($this->buildLink('account/invitation', null, ['code' => $Code->code]));

        }
        else
        {
            $Invitations = $this->Invitation($visitor);
            $options = \XF::options();
            $page = $this->filterPage();
            $perPage = $options->xs_is_perPage_account;
            $Codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->order('code_id', 'DESC')
                ->where([
                    'user_id'=> $visitor->user_id,
                    ['type_code', '!=', '2']
                ]);
            $Codes->limitByPage($page, $perPage);
            $viewParams = [
                'Invitations' => $Invitations,
                'code' => $this->filter('code', 'str'),
                'Codes' => $Codes->fetch(),
                'total' => $Codes->total(),
                'page' => $page,
                'perPage' => $perPage
            ];

            $view = $this->view('XenSoluce\InviteSystem:Invitation', 'xs_is_account_Invitation', $viewParams);
            return $this->addAccountWrapperParams($view, 'xs_Invitation');
        }
    }
    protected function Invitation(\XF\Entity\User $visitor)
    {

        $Tokens = $this->finder('XenSoluce\InviteSystem:Token')
            ->order('token_id', 'DESC');
        $Invitations['tokenGenerate'] = null;
        $Invitations['total'] = 0;
        $Invitations += $this->InvitationPerXDay($visitor);
        foreach ($Tokens->fetch() as $Token)
        {
            $isCount = 0;
            $isToken = false;
            $isNumber = false;
            $Codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where([
                    'user_id'=> $visitor->user_id,
                    'token_id' => $Token->token_id
                ]);
            if($Token->number_use == '1')
            {
                $isToken = empty($Codes->fetchOne());
            }
            elseif($Token->number_use > '1')
            {
                if(count($Codes->fetch()) < $Token->number_use)
                {
                    $isToken = true;
                }
                $isNumber = true;
            }
            if(in_array($visitor->user_id, $Token->user) && $isToken &&  $Token->type_token == '2')
            {
                $Invitations['tokenGenerate'][$Token->token_id] = [
                    'token' => $Token->token,
                    'tokenID' => $Token->token_id,
                    'tokenName' => $Token->title
                ];
                if(!$isNumber)
                {
                    $isCount = 1;
                }
                else
                {
                    $isCount = 2;
                }
            }
            if(in_array($visitor->user_group_id, $Token->user) && $isToken &&  $Token->type_token == '1')
            {
                $Invitations['tokenGenerate'][$Token->token_id] = [
                    'token' => $Token->token,
                    'tokenID' => $Token->token_id,
                    'tokenName' => $Token->title
                ];
                if(!$isNumber)
                {
                    $isCount = 1;
                }
                else
                {
                    $isCount = 2;
                }
            }
            foreach ($visitor->secondary_group_ids as $group)
            {
                if(in_array($group, $Token->user) && $isToken &&  $Token->type_token == '1')
                {
                    $Invitations['tokenGenerate'][$Token->token_id] = [
                        'token' => $Token->token,
                        'tokenID' => $Token->token_id,
                        'tokenName' => $Token->title
                    ];
                    if(!$isNumber)
                    {
                        $isCount = 1;
                    }
                    else
                    {
                        $isCount = 2;
                    }
                }
            }
            if($isCount === 1)
            {
                $Invitations['total']++;
            }
            elseif($isCount === 2)
            {
                $Invitations['total'] += $Token->number_use - count($Codes->fetch());
            }
        }

        return $Invitations;
    }
    protected function InvitationPerXDay(\XF\Entity\User $visitor)
    {
        $options = \XF::options();
        $XDay =  $visitor->hasPermission('xs_is', 'xs_is_x_d');
        $invitationsPerXDay = $visitor->hasPermission('xs_is', 'xs_is_can_invite_per_x_d');

        $timeZone = new \DateTimeZone($options->guestTimeZone);
        $Invitations['Between'] = true;
        if($invitationsPerXDay > 0)
        {
            $dateTime = new \DateTime('+' . $XDay . 'day 00:00', $timeZone);
            $CodeInvitationCount = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where([
                    'user_id'=> $visitor->user_id,
                    ['type_code', '!=', ['2', '5', '6']]
                ])
                ->dateBetweenInvitation($dateTime->format('U'), \XF::$time)
                ->total();
            if($invitationsPerXDay <= $CodeInvitationCount)
            {
                $Invitations = [
                    'Between' => false,
                    'PerMonth' => $invitationsPerXDay
                ];
            }
        }
        return $Invitations;
    }
}
