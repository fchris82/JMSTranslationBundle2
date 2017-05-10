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

namespace JMS\TranslationBundle\Translation\Extractor\File;

use JMS\TranslationBundle\Annotation\AltTrans;
use JMS\TranslationBundle\Annotation\TransArrayKeys;
use JMS\TranslationBundle\Annotation\TransArrayValues;
use JMS\TranslationBundle\Annotation\TransString;
use JMS\TranslationBundle\Exception\RuntimeException;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Annotation\Meaning;
use JMS\TranslationBundle\Annotation\Desc;
use JMS\TranslationBundle\Annotation\Ignore;
use Doctrine\Common\Annotations\DocParser;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Extractor\FileVisitorInterface;
use JMS\TranslationBundle\Logger\LoggerAwareInterface;
use JMS\TranslationBundle\Translation\FileSourceFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class AnnotationExtractor
 *
 * Parse translations by Trans* annotations
 *
 * @package JMS\TranslationBundle\Translation\Extractor\File
 */
class AnnotationExtractor implements FileVisitorInterface, LoggerAwareInterface, NodeVisitor
{
    const ARRAY_TARGET_KEY = 'key';
    const ARRAY_TARGET_VALUE = 'value';

    /**
     * @var FileSourceFactory
     */
    private $fileSourceFactory;

    /**
     * @var DocParser
     */
    private $docParser;

    /**
     * @var NodeTraverser
     */
    private $traverser;

    /**
     * @var \SplFileInfo
     */
    private $file;

    /**
     * @var MessageCatalogue
     */
    private $catalogue;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FormExtractor constructor.
     * @param DocParser $docParser
     * @param FileSourceFactory $fileSourceFactory
     */
    public function __construct(DocParser $docParser, FileSourceFactory $fileSourceFactory)
    {
        $this->docParser = $docParser;
        $this->fileSourceFactory = $fileSourceFactory;
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this);
    }

    /**
     * @param Node $node
     * @return null|Node|void
     */
    public function enterNode(Node $node)
    {
        if (!$this->nodeHasTransAnnotation($node)) {
            return;
        }

        $docComments = $this->docParser->parse($node->getDocComment(), 'file '.$this->file.' near line '.$node->getLine());
        foreach ($docComments as $annot) {
            if ($annot instanceof TransString) {
                $this->parseStringItem($node, $annot->domain);
            } elseif ($annot instanceof TransArrayKeys) {
                $this->parseArray($node, $annot->domain, self::ARRAY_TARGET_KEY);
            } elseif ($annot instanceof TransArrayValues) {
                $this->parseArray($node, $annot->domain, self::ARRAY_TARGET_VALUE);
            }
        }
    }

    /**
     * Check the node has Trans* annotation.
     *
     * @param Node $node
     * @return bool
     */
    protected function nodeHasTransAnnotation(Node $node)
    {
        if (!$node->hasAttribute('comments')) {
            return false;
        }

        $docComment = $node->getDocComment();
        return $docComment && strpos($docComment, '@Trans');
    }

    /**
     * Find string in (sub)node
     *
     * @param Node $node
     * @param string $domain
     */
    protected function parseStringItem(Node $node, $domain)
    {
        $ignore = false;
        $desc = $meaning = null;
        $alternativeTranslations = [];
        if (null !== $docComment = $node->getDocComment()) {
            foreach ($this->docParser->parse($docComment, 'file '.$this->file.' near line '.$node->getLine()) as $annot) {
                if ($annot instanceof Ignore) {
                    $ignore = true;
                } elseif ($annot instanceof Desc) {
                    $desc = $annot->text;
                } elseif ($annot instanceof Meaning) {
                    $meaning = $annot->text;
                } elseif ($annot instanceof AltTrans) {
                    $alternativeTranslations[$annot->locale] = $annot->text;
                }
            }
        }

        $stringNode = $this->findSubNode($node, Node\Scalar\String_::class);
        if (!$stringNode) {
            return;
        }

        $message = new Message($stringNode->value, $domain);
        $message
            ->setDesc($desc)
            ->setMeaning($meaning)
            ->addSource($this->fileSourceFactory->create($this->file, $stringNode->getLine()))
            ->setAlternativeTranslations($alternativeTranslations)
        ;
        $this->catalogue->add($message);
    }

    /**
     * @param Node $node
     * @param string $domain
     * @param string $target self::ARRAY_TARGET_*
     */
    protected function parseArray(Node $node, $domain, $target)
    {
        $arrayNode = $this->findSubNode($node, Node\Expr\Array_::class);
        if (!$arrayNode) {
            return;
        }
        /** @var Node\Expr\ArrayItem $arrayItem */
        foreach ($arrayNode->items as $arrayItem) {
            /** @var Node\Scalar\String_ $stringNode */
            $stringNode = $target == self::ARRAY_TARGET_KEY
                ? $arrayItem->key
                : $arrayItem->value;
            // If arrayItem has docComment but the current string node doesn't, then set it
            if (!$stringNode->getDocComment() && $arrayItem->getDocComment()) {
                $stringNode->setAttribute('comments', $arrayItem->getAttribute('comments'));
            }

            $this->parseStringItem($stringNode, $domain);
        }
    }

    /**
     * Find in node the subnode with setted class.
     *
     * @param Node $node
     * @param string $nodeClass Finded node class
     * @return Node|void
     */
    protected function findSubNode(Node $node, $nodeClass)
    {
        if (get_class($node) == $nodeClass) {
            return $node;
        }
        if (isset($node->expr) && is_object($node->expr) && get_class($node->expr) == $nodeClass) {
            return $node->expr;
        }
        if (isset($node->value) && is_object($node->value) && get_class($node->value) == $nodeClass) {
            return $node->value;
        }

        return;
    }

    /**
     * @param \SplFileInfo $file
     * @param MessageCatalogue $catalogue
     * @param array $ast
     */
    public function visitPhpFile(\SplFileInfo $file, MessageCatalogue $catalogue, array $ast)
    {
        $this->file = $file;
        $this->catalogue = $catalogue;
        $this->traverser->traverse($ast);
    }

    /**
     * @param Node $node
     * @return null|\PhpParser\Node[]|void
     */
    public function leaveNode(Node $node)
    {
    }

    /**
     * @param array $nodes
     * @return null|\PhpParser\Node[]|void
     */
    public function beforeTraverse(array $nodes)
    {
    }

    /**
     * @param array $nodes
     * @return null|\PhpParser\Node[]|void
     */
    public function afterTraverse(array $nodes)
    {
    }

    /**
     * @param \SplFileInfo $file
     * @param MessageCatalogue $catalogue
     */
    public function visitFile(\SplFileInfo $file, MessageCatalogue $catalogue)
    {
    }

    /**
     * @param \SplFileInfo $file
     * @param MessageCatalogue $catalogue
     * @param \Twig_Node $ast
     */
    public function visitTwigFile(\SplFileInfo $file, MessageCatalogue $catalogue, \Twig_Node $ast)
    {
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
