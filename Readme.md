# TYPO3 Extension `pforum`

[![Packagist][packagist-logo-stable]][extension-packagist-url]
[![Latest Stable Version][extension-build-shield]][extension-ter-url]
[![License][LICENSE_BADGE]][extension-packagist-url]
[![Total Downloads][extension-downloads-badge]][extension-packagist-url]
[![Monthly Downloads][extension-monthly-downloads]][extension-packagist-url]
[![TYPO3 12.4][TYPO3-shield]][TYPO3-12-url]

![Build Status][extension-ci-shield]

Pforum is a little lightweight forum for TYPO3

## 1 Features

* Create forums, topics and postings
* You can assign images to topics and postings
* It is possible to add topics/post as anonymous user, is configured

## 2 Usage

### 2.1 Installation

#### Installation using Composer

The recommended way to install the extension is using Composer.

Run the following command within your Composer based TYPO3 project:

```
composer require jweiland/pforum
```

#### Installation as extension from TYPO3 Extension Repository (TER)

Download and install `pforum` with the extension manager module.

### 2.2 Minimal setup

1) Include the static TypoScript of the extension.
2) Create forum records on a sysfolder.
3) Create a plugin on a page and select at least the sysfolder as startingpoint.

<!-- MARKDOWN LINKS & IMAGES -->

[extension-build-shield]: https://poser.pugx.org/jweiland/pforum/v/stable.svg?style=for-the-badge

[extension-ci-shield]: https://github.com/jweiland-net/pforum/actions/workflows/ci.yml/badge.svg

[extension-downloads-badge]: https://poser.pugx.org/jweiland/pforum/d/total.svg?style=for-the-badge

[extension-monthly-downloads]: https://poser.pugx.org/jweiland/pforum/d/monthly?style=for-the-badge

[extension-ter-url]: https://extensions.typo3.org/extension/pforum/

[extension-packagist-url]: https://packagist.org/packages/jweiland/pforum/

[packagist-logo-stable]: https://img.shields.io/badge/--grey.svg?style=for-the-badge&logo=packagist&logoColor=white

[TYPO3-12-url]: https://get.typo3.org/version/12

[TYPO3-shield]: https://img.shields.io/badge/TYPO3-12.4-green.svg?style=for-the-badge&logo=typo3

[LICENSE_BADGE]: https://img.shields.io/github/license/jweiland-net/pforum?label=license&style=for-the-badge
