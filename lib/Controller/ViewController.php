<?php
declare(strict_types=1);

namespace OCA\Xmlviewer\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IShare;
use OCP\Share\IManager as IShareManager;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Share\Exceptions\ShareNotFound;

class ViewController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IShareManager $shareManager,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(int $fileId, string $backUrl = ''): TemplateResponse|RedirectResponse {
		$backUrl = (string)$this->request->getParam('backUrl', $backUrl);
		$userId = $this->userSession->getUser()?->getUID();
		
		if ($userId === null) {
			return $this->errorResponse($this->l10n->t('Please sign in or open the file through a public share.'), $backUrl);
		}

		$file = null;
		
		try {
			foreach ($this->rootFolder->getUserFolder($userId)->getById($fileId) as $node) {
				if ($node instanceof File) {
					$file = $node;
					break;
				}
			}
		} catch (\OCP\Files\NotFoundException|\OCP\Files\NotPermittedException $e) {
			return $this->errorResponse($this->l10n->t('The XML file could not be found.'), $backUrl);
		}
		
		if ($file === null) {
			return $this->errorResponse($this->l10n->t('The XML file could not be found.'), $backUrl);
		}
		
		return $this->renderFile($file, $fileId, $backUrl);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function download(int $fileId): Response {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return $this->emptyResponse(403);
		}
		
		try {
			foreach ($this->rootFolder->getUserFolder($userId)->getById($fileId) as $node) {
				if ($node instanceof File) {
					return $this->downloadResponse($node);
				}
			}
		} catch (\OCP\Files\NotFoundException|\OCP\Files\NotPermittedException $e) {}
		
		return $this->emptyResponse(404);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function parserStyle(string $parser): Response {
		if (preg_match('/^[A-Za-z0-9_-]+$/D', $parser) !== 1) {
			return new Response(404);
		}
		
		$parserRoot = realpath(__DIR__ . '/../Parser');
		$parserDirectory = $parserRoot === false ? false : realpath($parserRoot . DIRECTORY_SEPARATOR . $parser);
		$stylePath = $parserDirectory === false ? false : realpath($parserDirectory . DIRECTORY_SEPARATOR . 'style.css');
		
		if ($parserRoot === false
			|| $parserDirectory === false
			|| $stylePath === false
			|| dirname($stylePath) !== $parserDirectory
			|| !str_starts_with($parserDirectory, $parserRoot . DIRECTORY_SEPARATOR)
			|| !is_file($stylePath)) {
			return new Response(404);
		}

		return new DataDisplayResponse(
			(string)file_get_contents($stylePath),
			200,
			[
				'Content-Type' => 'text/css; charset=utf-8',
				'Cache-Control' => 'public, max-age=3600',
			],
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function downloadSharedFile(string $shareToken, int $fileId): Response {
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			$sharedNode = $share->getNode();
			
			if ($share->getShareType() !== IShare::TYPE_LINK || ($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
				return $this->emptyResponse(403);
			}
			
			if ($sharedNode instanceof File && $sharedNode->getId() === $fileId) {
				return $this->downloadResponse($sharedNode);
			}
			
			if ($sharedNode instanceof Folder) {
				foreach ($sharedNode->getById($fileId) as $node) {
					if ($node instanceof File && $this->isWithinSharedFolder($node, $sharedNode)) {
						return $this->downloadResponse($node);
					}
				}
			}
		} catch (ShareNotFound|\OCP\Files\NotFoundException|\OCP\Files\NotPermittedException $e) {}
		
		return $this->emptyResponse(404);
	}

	private function emptyResponse(int $status): Response {
		return new Response($status);
	}

	private function downloadResponse(File $file): DataDownloadResponse {
		$response = new DataDownloadResponse($file->getContent(), $file->getName(), 'application/xml');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Cache-Control', 'private, no-store');
		
		return $response;
	}

	// Resolve only through a public-link share; this path never accesses the user folder.
	public function openSharedFile(string $shareToken, int $fileId, string $backUrl = ''): TemplateResponse|RedirectResponse {
		$file = null;
		
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			$sharedNode = $share->getNode();
			if ($share->getShareType() === IShare::TYPE_LINK
				&& ($share->getPermissions() & Constants::PERMISSION_READ) !== 0) {
				if ($sharedNode instanceof File && $sharedNode->getId() === $fileId) {
					$file = $sharedNode;
				} elseif ($sharedNode instanceof Folder) {
					foreach ($sharedNode->getById($fileId) as $node) {
						if ($node instanceof File && $this->isWithinSharedFolder($node, $sharedNode)) {
							$file = $node;
							break;
						}
					}
				}
			}
		} catch (ShareNotFound|\OCP\Files\NotFoundException|\OCP\Files\NotPermittedException $e) {
			return $this->errorResponse($this->l10n->t('The share or XML file could not be found.'), $backUrl, true);
		}
		
		if ($file === null) {
			return $this->errorResponse($this->l10n->t('The XML file could not be found or is not part of this share.'), $backUrl, true);
		}
		
		$isDirectShare = $sharedNode instanceof File && $sharedNode->getId() === $fileId;
		
		return $this->renderFile($file, $fileId, $backUrl, $shareToken, !$isDirectShare);
	}

	private function renderFile(File $file, int $fileId, string $backUrl, string $shareToken = '', bool $showClose = true): TemplateResponse|RedirectResponse {
		if ($file->getSize() > 5 * 1024 * 1024) {
			return $this->nativeViewerResponse($fileId, $backUrl, $shareToken);
		}

		$source = $file->getContent();
		$previousLibxmlErrorMode = libxml_use_internal_errors(true);
		
		try {
			$xml = simplexml_load_string($source, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previousLibxmlErrorMode);
		}
		
		if (!$xml instanceof \SimpleXMLElement) {
			return $this->nativeViewerResponse($fileId, $backUrl, $shareToken);
		}
		
		$parser = (new \OCA\Xmlviewer\Parser\XmlParserRegistry())->findParser($xml);
		$viewData = $parser?->parse($xml, $source);
		
		if ($parser === null || $viewData === null) {
			return $this->nativeViewerResponse($fileId, $backUrl, $shareToken);
		}
		
		$templatePath = $parser->getTemplatePath();
		$parserName = basename(dirname($templatePath));
		$parserStylePath = dirname($templatePath) . DIRECTORY_SEPARATOR . 'style.css';
		$parserStylesheetUrl = is_file($parserStylePath)
			? $this->urlGenerator->linkToRoute('xmlviewer.view.parserStyle', ['parser' => $parserName])
			: '';

		return new TemplateResponse('xmlviewer', 'parser', [
			'title' => $file->getName(),
			'templatePath' => $templatePath,
			'parserStylesheetUrl' => $parserStylesheetUrl,
			'data' => $viewData,
			'backUrl' => $this->validatedFilesUrl($backUrl),
			'isPublic' => $shareToken !== '',
			'showClose' => $showClose,
			'backUrl' => $this->validatedFilesUrl($backUrl),
			'downloadUrl' => $shareToken !== ''
				? $this->urlGenerator->linkToRoute('xmlviewer.publicview.download', ['token' => $shareToken, 'fileId' => $fileId])
				: $this->urlGenerator->linkToRoute('xmlviewer.view.download', ['fileId' => $fileId]),
		], $shareToken !== '' ? 'guest' : 'user');
	}

	private function errorResponse(string $message, string $backUrl, bool $guest = false): TemplateResponse {
		return new TemplateResponse('xmlviewer', 'error', [
			'message' => $message,
			'backUrl' => $this->validatedFilesUrl($backUrl),
		], $guest ? 'guest' : 'error');
	}

	private function nativeViewerResponse(int $fileId, string $backUrl, string $shareToken = ''): RedirectResponse {
		if ($shareToken !== '') {
			try {
				$share = $this->shareManager->getShareByToken($shareToken);
				$params = ['token' => $shareToken];
				$sharedNode = $share->getNode();
				
				if ($sharedNode instanceof Folder) {
					$sharedPath = rtrim($sharedNode->getPath(), '/');
					$filePath = $this->getFilePathFromShare($fileId, $sharedNode);
					
					if ($filePath === null) {
						return new RedirectResponse($this->validatedFilesUrl($backUrl));
					}
					
					$relativePath = ltrim(substr($filePath, strlen($sharedPath)), '/');
					$directory = dirname($relativePath);
					$params['path'] = $directory === '.' ? '/' : '/' . $directory;
					$params['openfile'] = $fileId;
					$params['xmlviewerFallback'] = $fileId;
					$params['xmlviewerFallbackType'] = 'folder';
				} elseif ($sharedNode instanceof File && $sharedNode->getId() === $fileId) {
					// Prevent the direct-share auto-parser from opening this same
					// unsupported XML file again and creating a redirect loop.
					$params['xmlviewerFallback'] = $fileId;
					$params['xmlviewerFallbackType'] = 'direct';
				}
				
				$nativeUrl = $this->urlGenerator->linkToRoute(
					'files_sharing.sharecontroller.showShare',
					$params,
			);
			
			return new RedirectResponse($nativeUrl);
			} catch (ShareNotFound|\OCP\Files\NotFoundException|\OCP\Files\NotPermittedException $e) {
				return new RedirectResponse($this->validatedFilesUrl($backUrl));
			}
		}
		$filesUrl = $this->validatedFilesUrl($backUrl);
		$parts = parse_url($filesUrl) ?: [];
		$path = $parts['path'] ?? '';
		$filesMarker = '/apps/files/';
		$position = strrpos($path, $filesMarker);
		
		if ($position === false) {
			return new RedirectResponse($filesUrl);
		}

		$filesBase = substr($path, 0, $position + strlen($filesMarker));
		$query = [];
		parse_str($parts['query'] ?? '', $query);
		unset($query['fileid']);
		$query['openfile'] = 'true';
		$query['xmlviewerFallback'] = (string)$fileId;
		$nativeUrl = $filesBase . 'files/' . $fileId . '?' . http_build_query($query);
		
		return new RedirectResponse($nativeUrl);
	}

	private function validatedFilesUrl(string $url): string {
		$fallback = $this->urlGenerator->linkToRoute('files.view.index');
		
		if ($url === '' || str_contains($url, "\r") || str_contains($url, "\n")) {
			return $fallback;
		}
		
		$parts = parse_url($url);
		
		if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
			return $fallback;
		}
		
		$path = $parts['path'] ?? '';
		$isFilesUrl = str_contains($path, '/apps/files/');
		$isPublicShareUrl = preg_match('~(?:^|/)s/[^/]+(?:/\\d+)?$~', $path) === 1;
		
		if (!str_starts_with($path, '/') || str_starts_with($path, '//') || (!$isFilesUrl && !$isPublicShareUrl)) {
			return $fallback;
		}
		
		$query = [];
		parse_str($parts['query'] ?? '', $query);
		
		if (str_contains($path, '/apps/files/')) {
			// Closing the table view should return to the Files list, not reopen
			// a file viewer route that happened to be active before this view.
			$filesMarker = '/apps/files/';
			$position = strrpos($path, $filesMarker);
			
			if ($position !== false) {
				$path = substr($path, 0, $position + strlen($filesMarker));
			}
			
			unset($query['fileid'], $query['openfile']);
		} elseif (preg_match('~(?:^|/)s/[^/]+$~', $path) === 1) {
			unset($query['openfile'], $query['xmlviewerFallback'], $query['xmlviewerFallbackType']);
		}
		
		return $path
			. ($query !== [] ? '?' . http_build_query($query) : '')
			. (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
	}

	private function isWithinSharedFolder(File $file, Folder $folder): bool {
		$folderPath = rtrim($folder->getPath(), '/');
		$filePath = $file->getPath();
		
		return $folderPath === '' || $folderPath === '/'
			? str_starts_with($filePath, '/')
			: str_starts_with($filePath, $folderPath . '/');
	}

	private function getFilePathFromShare(int $fileId, Folder $sharedFolder): ?string {
		foreach ($sharedFolder->getById($fileId) as $node) {
			if ($node instanceof File && $this->isWithinSharedFolder($node, $sharedFolder)) {
				return $node->getPath();
			}
		}
		
		return null;
	}
}
