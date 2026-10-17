<?php
declare(strict_types=1);

namespace OCA\Xmlviewer\Controller;

use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\PublicShareController;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IL10N;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Share\Exceptions\ShareNotFound;

class PublicViewController extends PublicShareController {
	private ?IShare $share = null;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IShareManager $shareManager,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request, $session);
	}

	protected function isPasswordProtected(): bool {
		$share = $this->getShare();
		
		return $share !== null && $share->getPassword() !== null && $share->getPassword() !== '';
	}

	protected function getPasswordHash(): string {
		return (string)($this->getShare()?->getPassword() ?? '');
	}

	public function isValidToken(): bool {
		$share = $this->getShare();
		
		return $share !== null
			&& $share->getShareType() === IShare::TYPE_LINK
			&& ($share->getPermissions() & \OCP\Constants::PERMISSION_READ) !== 0;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function root(string $token, string $backUrl = ''): TemplateResponse|RedirectResponse {
		$share = $this->getShare();
		$node = $share?->getNode();
		
		if ($node instanceof File) {
			return $this->index($token, (int)$node->getId(), $backUrl);
		}

		$shareUrl = $this->urlGenerator->linkToRoute(
			'files_sharing.sharecontroller.showShare',
			['token' => $token],
		);
		
		return new RedirectResponse($shareUrl . '#xmlviewerFallback=folder');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function index(string $token, int $fileId, string $backUrl = ''): TemplateResponse|RedirectResponse {
		$viewController = new ViewController(
			'xmlviewer',
			$this->request,
			$this->rootFolder,
			$this->userSession,
			$this->urlGenerator,
			$this->shareManager,
			$this->l10n,
		);
		
		return $viewController->openSharedFile($token, $fileId, $backUrl);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function download(string $token, int $fileId): \OCP\AppFramework\Http\Response {
		$viewController = new ViewController(
			'xmlviewer', $this->request, $this->rootFolder, $this->userSession,
			$this->urlGenerator, $this->shareManager, $this->l10n,
		);
		
		return $viewController->downloadSharedFile($token, $fileId);
	}

	private function getShare(): ?IShare {
		if ($this->share !== null) {
			return $this->share;
		}
		
		try {
			$this->share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound) {
			return null;
		}
		
		return $this->share;
	}
}
