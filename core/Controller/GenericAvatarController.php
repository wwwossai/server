<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Core\Controller;

use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAvatarManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;

class GenericAvatarController extends OCSController {

	/** @var IAvatarManager */
	protected $avatarManager;

	/** @var IL10N */
	protected $l;

	/** @var ILogger */
	protected $logger;

	public function __construct($appName,
								IRequest $request,
								IAvatarManager $avatarManager,
								IL10N $l10n,
								ILogger $logger) {
		parent::__construct($appName, $request);

		$this->avatarManager = $avatarManager;
		$this->l = $l10n;
		$this->logger = $logger;
	}

	/**
	 * @PublicPage
	 *
	 * @param string $avatarType
	 * @param string $avatarId
	 * @param int $size
	 * @return JSONResponse|FileDisplayResponse
	 */
	public function getAvatar(string $avatarType, string $avatarId, int $size) {
		// min/max size
		if ($size > 2048) {
			$size = 2048;
		} elseif ($size <= 0) {
			$size = 64;
		}

		try {
			$avatar = $this->avatarManager->getGenericAvatar($avatarType, $avatarId);
			$avatarFile = $avatar->getFile($size);
			$response = new FileDisplayResponse(
				$avatarFile,
				Http::STATUS_OK,
				[
					'Content-Type' => $avatarFile->getMimeType(),
					'X-NC-IsCustomAvatar' => (int)$avatar->isCustomAvatar()
				]
			);
		} catch (\Exception $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		return $response;
	}

	/**
	 * @PublicPage
	 *
	 * @param string $path
	 * @return JSONResponse
	 */
	public function setAvatar(string $avatarType, string $avatarId) {
		$files = $this->request->getUploadedFile('files');

		if (is_null($files)) {
			return new JSONResponse(
				['data' => ['message' => $this->l->t('No file provided')]],
				Http::STATUS_BAD_REQUEST
			);
		}

		if (
			$files['error'][0] !== 0 ||
			!is_uploaded_file($files['tmp_name'][0]) ||
			\OC\Files\Filesystem::isFileBlacklisted($files['tmp_name'][0])
		) {
			return new JSONResponse(
				['data' => ['message' => $this->l->t('Invalid file provided')]],
				Http::STATUS_BAD_REQUEST
			);
		}

		if ($files['size'][0] > 20 * 1024 * 1024) {
			return new JSONResponse(
				['data' => ['message' => $this->l->t('File is too big')]],
				Http::STATUS_BAD_REQUEST
			);
		}

		$content = file_get_contents($files['tmp_name'][0]);
		unlink($files['tmp_name'][0]);

		$image = new \OC_Image();
		$image->loadFromData($content);

		try {
			$avatar = $this->avatarManager->getGenericAvatar($avatarType, $avatarId);
			$avatar->set($image);
			return new JSONResponse(
				['status' => 'success']
			);
		} catch (\OC\NotSquareException $e) {
			return new JSONResponse(
				['data' => ['message' => $this->l->t('Crop is not square')]],
				Http::STATUS_BAD_REQUEST
			);
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'core']);
			return new JSONResponse(
				['data' => ['message' => $this->l->t('An error occurred. Please contact your admin.')]],
				Http::STATUS_BAD_REQUEST
			);
		}
	}

	/**
	 * @PublicPage
	 *
	 * @return JSONResponse
	 */
	public function deleteAvatar(string $avatarType, string $avatarId) {
		try {
			$avatar = $this->avatarManager->getGenericAvatar($avatarType, $avatarId);
			$avatar->remove();
			return new JSONResponse();
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'core']);
			return new JSONResponse(
				['data' => ['message' => $this->l->t('An error occurred. Please contact your admin.')]],
				Http::STATUS_BAD_REQUEST
			);
		}
	}
}
