<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Controller;

use Init\Thw\Ovssp\Service\RightsService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\SetCookieService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class PortalController extends ActionController
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly RightsService $rightsService,
        private readonly Context $context,
        private readonly PasswordHashFactory $passwordHashFactory,
    ) {}

    public function loginAction(): ResponseInterface
    {
        // Handle logout
        if ($this->request->getMethod() === 'POST') {
            $parsedBody = $this->request->getParsedBody();
            if (is_array($parsedBody) && ($parsedBody['logintype'] ?? '') === 'logout') {
                $frontendUser = $this->request->getAttribute('frontend.user');
                if ($frontendUser instanceof FrontendUserAuthentication) {
                    $frontendUser->logoff();
                }

                return $this->redirect('login');
            }
        }

        $currentUser = $this->getCurrentUser();
        if ($currentUser !== null) {
            $this->view->assign('loggedIn', true);
            $this->view->assign('currentUser', $currentUser);

            return $this->htmlResponse();
        }

        // Handle login
        if ($this->request->getMethod() === 'POST') {
            $parsedBody = $this->request->getParsedBody();
            if (is_array($parsedBody) && ($parsedBody['logintype'] ?? '') === 'login') {
                $username = is_string($parsedBody['user'] ?? null) ? $parsedBody['user'] : '';
                $password = is_string($parsedBody['pass'] ?? null) ? $parsedBody['pass'] : '';

                if ($username !== '' && $password !== '') {
                    $userRecord = $this->findFeUserByUsername($username);
                    if ($userRecord !== null && $this->verifyPassword($password, (string) $userRecord['password'])) {
                        $frontendUser = $this->request->getAttribute('frontend.user');
                        if ($frontendUser instanceof FrontendUserAuthentication) {
                            $session = $frontendUser->createUserSession($userRecord);
                            $ref = new \ReflectionProperty($frontendUser, 'userSession');
                            $ref->setValue($frontendUser, $session);
                            $frontendUser->user = $userRecord;
                            $this->context->setAspect('frontend.user', $frontendUser->createUserAspect());

                            // Set session cookie directly on the response
                            $normalizedParams = $this->request->getAttribute('normalizedParams');
                            $cookieService = SetCookieService::create($frontendUser->name, $frontendUser->loginType);
                            $cookie = $cookieService->setSessionCookie($session, $normalizedParams);

                            $this->view->assign('loggedIn', true);
                            $this->view->assign('currentUser', $userRecord);

                            $response = $this->htmlResponse();
                            if ($cookie !== null) {
                                $response = $response->withAddedHeader('Set-Cookie', $cookie->__toString());
                            }

                            return $response;
                        }
                    }
                }

                $this->addFlashMessage(
                    'Login failed. Invalid username or password.',
                    'Login',
                    \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
                );

                return $this->redirect('login');
            }
        }

        return $this->htmlResponse();
    }

    public function indexAction(): ResponseInterface
    {
        $currentUser = $this->getCurrentUser();
        if ($currentUser === null) {
            $this->view->assign('accessDenied', true);

            return $this->htmlResponse();
        }

        $portalAccess = is_numeric($currentUser['thw_portal_access']) ? (int) $currentUser['thw_portal_access'] : 0;
        if ($portalAccess === 0) {
            $this->view->assign('accessDenied', true);

            return $this->htmlResponse();
        }

        $orgunitUid = is_numeric($currentUser['thw_orgunit']) ? (int) $currentUser['thw_orgunit'] : 0;

        if ($portalAccess === 2 && $orgunitUid > 0) {
            $users = $this->getUsersForOrgUnit($orgunitUid);
            $this->view->assign('users', $users);
            $this->view->assign('isAdmin', true);
        } else {
            $this->view->assign('isAdmin', false);
        }

        $rights = $this->rightsService->getCombinedRightsForUser((int) $currentUser['uid']);
        $roleUids = $this->rightsService->getRoleUidsForUser((int) $currentUser['uid']);
        $allRoles = $this->rightsService->getAllRoles();

        $this->view->assign('currentUser', $currentUser);
        $this->view->assign('rights', $rights);
        $this->view->assign('roleUids', $roleUids);
        $this->view->assign('allRoles', $allRoles);

        return $this->htmlResponse();
    }

    public function userDetailAction(int $userUid = 0): ResponseInterface
    {
        $currentUser = $this->getCurrentUser();
        if ($currentUser === null || !$this->isOvAdmin($currentUser)) {
            $this->view->assign('accessDenied', true);

            return $this->htmlResponse();
        }

        $targetUser = $this->getUserByUid($userUid);
        if ($targetUser === null) {
            $this->view->assign('userNotFound', true);

            return $this->htmlResponse();
        }

        $currentOrgunit = is_numeric($currentUser['thw_orgunit']) ? (int) $currentUser['thw_orgunit'] : 0;
        $targetOrgunit = is_numeric($targetUser['thw_orgunit']) ? (int) $targetUser['thw_orgunit'] : 0;
        if ($currentOrgunit === 0 || $currentOrgunit !== $targetOrgunit) {
            $this->view->assign('accessDenied', true);

            return $this->htmlResponse();
        }

        $rights = $this->rightsService->getCombinedRightsForUser($userUid);
        $roleUids = $this->rightsService->getRoleUidsForUser($userUid);
        $allRoles = $this->rightsService->getAllRoles();

        $this->view->assign('targetUser', $targetUser);
        $this->view->assign('rights', $rights);
        $this->view->assign('roleUids', $roleUids);
        $this->view->assign('allRoles', $allRoles);

        return $this->htmlResponse();
    }

    public function assignRoleAction(int $userUid = 0, int $roleUid = 0): ResponseInterface
    {
        $currentUser = $this->getCurrentUser();
        if ($currentUser === null || !$this->isOvAdmin($currentUser)) {
            return $this->redirect('index');
        }

        if (!$this->isUserInSameOrgUnit($currentUser, $userUid)) {
            return $this->redirect('index');
        }

        $targetUser = $this->getUserByUid($userUid);
        if ($targetUser !== null && !empty($targetUser['disable'])) {
            return $this->redirect('userDetail', null, null, ['userUid' => $userUid]);
        }

        $this->rightsService->assignRole($userUid, $roleUid);

        return $this->redirect('userDetail', null, null, ['userUid' => $userUid]);
    }

    public function removeRoleAction(int $userUid = 0, int $roleUid = 0): ResponseInterface
    {
        $currentUser = $this->getCurrentUser();
        if ($currentUser === null || !$this->isOvAdmin($currentUser)) {
            return $this->redirect('index');
        }

        if (!$this->isUserInSameOrgUnit($currentUser, $userUid)) {
            return $this->redirect('index');
        }

        $this->rightsService->removeRole($userUid, $roleUid);

        return $this->redirect('userDetail', null, null, ['userUid' => $userUid]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getCurrentUser(): ?array
    {
        $userAspect = $this->context->getAspect('frontend.user');
        if (!$userAspect->isLoggedIn()) {
            return null;
        }

        $userId = $userAspect->get('id');
        if (!is_int($userId) || $userId === 0) {
            return null;
        }

        return $this->getUserByUid($userId);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isOvAdmin(array $user): bool
    {
        $access = is_numeric($user['thw_portal_access']) ? (int) $user['thw_portal_access'] : 0;

        return $access === 2;
    }

    /**
     * @param array<string, mixed> $currentUser
     */
    private function isUserInSameOrgUnit(array $currentUser, int $targetUserUid): bool
    {
        $targetUser = $this->getUserByUid($targetUserUid);
        if ($targetUser === null) {
            return false;
        }
        $currentOrgunit = is_numeric($currentUser['thw_orgunit']) ? (int) $currentUser['thw_orgunit'] : 0;
        $targetOrgunit = is_numeric($targetUser['thw_orgunit']) ? (int) $targetUser['thw_orgunit'] : 0;

        return $currentOrgunit > 0 && $currentOrgunit === $targetOrgunit;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getUserByUid(int $uid): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');

        $row = $qb
            ->select('u.*', 'o.oe_code', 'o.name AS orgunit_name')
            ->from('fe_users', 'u')
            ->leftJoin(
                'u',
                'tx_thwovssp_domain_model_orgunit',
                'o',
                $qb->expr()->eq('u.thw_orgunit', $qb->quoteIdentifier('o.uid'))
            )
            ->where(
                $qb->expr()->eq('u.uid', $qb->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getUsersForOrgUnit(int $orgunitUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');

        return $qb
            ->select('uid', 'username', 'first_name', 'last_name', 'disable', 'thw_uid', 'thw_portal_access')
            ->from('fe_users')
            ->where(
                $qb->expr()->eq('thw_orgunit', $qb->createNamedParameter($orgunitUid, Connection::PARAM_INT)),
                $qb->expr()->gt('thw_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('disable', 'ASC')
            ->addOrderBy('last_name', 'ASC')
            ->addOrderBy('first_name', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findFeUserByUsername(string $username): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeAll();

        $row = $qb
            ->select('*')
            ->from('fe_users')
            ->where(
                $qb->expr()->eq('username', $qb->createNamedParameter($username)),
                $qb->expr()->eq('disable', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    private function verifyPassword(string $plaintext, string $hash): bool
    {
        $hashInstance = $this->passwordHashFactory->getDefaultHashInstance('FE');

        return $hashInstance->checkPassword($plaintext, $hash);
    }

    private function getFeUserStoragePid(): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeAll();

        $row = $qb
            ->select('pid')
            ->from('fe_users')
            ->where(
                $qb->expr()->gt('thw_uid', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) && is_numeric($row['pid']) ? (int) $row['pid'] : 0;
    }
}
