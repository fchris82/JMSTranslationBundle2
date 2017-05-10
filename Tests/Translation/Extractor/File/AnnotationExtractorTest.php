<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File;

use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Extractor\File\AnnotationExtractor;
use JMS\TranslationBundle\Translation\Extractor\File\DefaultPhpFileExtractor;

class AnnotationExtractorTest extends BasePhpFileExtractorTest
{
    public function testExtractController()
    {
        $catalogue = $this->extract('Controller.php');

        $fileSourceFactory = $this->getFileSourceFactory();
        $fixtureSplInfo = new \SplFileInfo(__DIR__.'/Fixture/Controller.php');

        $expected = new MessageCatalogue();

        $message = new Message('simple.text1');
        $message->addSource($fileSourceFactory->create($fixtureSplInfo, 94));
        $expected->add($message);

        $message = new Message('simple.text2');
        $message->addSource($fileSourceFactory->create($fixtureSplInfo, 95));
        $message->addAlternativeTranslation('en', 'Simple text 2');
        $expected->add($message);

        $message = new Message('subarray.value1', 'subarray');
        $message->addSource($fileSourceFactory->create($fixtureSplInfo, 100));
        $message->addAlternativeTranslation('en', 'Sub array value1');
        $expected->add($message);

        $message = new Message('subarray.value2', 'subarray');
        $message->addSource($fileSourceFactory->create($fixtureSplInfo, 102));
        $message->addAlternativeTranslation('en', 'Sub array value2');
        $expected->add($message);

        $message = new Message('user.role.partner');
        $message->addSource($fileSourceFactory->create($fixtureSplInfo, 108));
        $expected->add($message);

        $message = new Message('user.role.supporter');
        $message->addSource($fileSourceFactory->create($fixtureSplInfo, 109));
        $expected->add($message);

        $message = new Message('user.role.admin');
        $message->addSource($fileSourceFactory->create($fixtureSplInfo, 110));
        $expected->add($message);

        $this->assertEquals($expected, $catalogue);
    }

    protected function getDefaultExtractor()
    {
        return new AnnotationExtractor($this->getDocParser(), $this->getFileSourceFactory());
    }
}
