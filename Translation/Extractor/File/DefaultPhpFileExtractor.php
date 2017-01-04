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

use JMS\TranslationBundle\Exception\RuntimeException;
use Doctrine\Common\Annotations\DocParser;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Annotation\Meaning;
use JMS\TranslationBundle\Annotation\Desc;
use JMS\TranslationBundle\Annotation\Ignore;
use JMS\TranslationBundle\Translation\Extractor\FileVisitorInterface;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Logger\LoggerAwareInterface;
use JMS\TranslationBundle\Translation\FileSourceFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Node\Scalar\String_;
use Psr\Log\LoggerInterface;

/**
 * This parser can extract translation information from PHP files.
 *
 * It parses all calls that are made to a method named "trans".
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DefaultPhpFileExtractor implements LoggerAwareInterface, FileVisitorInterface, NodeVisitor
{
    /**
     * @var FileSourceFactory
     */
    protected $fileSourceFactory;

    /**
     * @var NodeTraverser
     */
    protected $traverser;

    /**
     * @var MessageCatalogue
     */
    protected $catalogue;

    /**
     * @var \SplFileInfo
     */
    protected $file;

    /**
     * @var DocParser
     */
    protected $docParser;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Node
     */
    protected $previousNode;

    /**
     * DefaultPhpFileExtractor constructor.
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
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Node $node
     * @return void
     */
    public function enterNode(Node $node)
    {
        $functions = [
            'trans',
            'transchoice',
            'addviolation',
            'addviolationat',
            'buildviolation',
        ];
        if (!$node instanceof Node\Expr\MethodCall
            || !is_string($node->name)
            || !in_array(strtolower($node->name), $functions)
            || count($node->args) == 0
        ) {
            $this->previousNode = $node;
            return;
        }

        $ignore = false;
        $desc = $meaning = null;
        if (null !== $docComment = $this->getDocCommentForNode($node)) {
            if ($docComment instanceof Doc) {
                $docComment = $docComment->getText();
            }
            foreach ($this->docParser->parse($docComment, 'file '.$this->file.' near line '.$node->getLine()) as $annot) {
                if ($annot instanceof Ignore) {
                    $ignore = true;
                } elseif ($annot instanceof Desc) {
                    $desc = $annot->text;
                } elseif ($annot instanceof Meaning) {
                    $meaning = $annot->text;
                }
            }
        }

        if (!$node->args[0]->value instanceof String_) {
            if ($ignore) {
                return;
            }

            $message = sprintf('Can only extract the translation id from a scalar string, but got "%s". Please refactor your code to make it extractable, or add the doc comment /** @Ignore */ to this code element (in %s on line %d).', get_class($node->args[0]->value), $this->file, $node->args[0]->value->getLine());

            if ($this->logger) {
                $this->logger->error($message);
                return;
            }

            throw new RuntimeException($message);
        }

        if(strpos($node->name, 'trans')===0) {
            $id = $node->args[0]->value->value;

            // domain index
            $domain_index = 'trans' === strtolower($node->name) ? 2 : 3;
            // $domain exists and not null!
            if (isset($node->args[$domain_index])
                && !($node->args[$domain_index]->value instanceof Node\Expr\ConstFetch && $node->args[$domain_index]->value->name == "null")
            ) {
                if (!$node->args[$domain_index]->value instanceof String_) {
                    if ($ignore) {
                        return;
                    }

                    $message = sprintf('Can only extract the translation domain from a scalar string, but got "%s". Please refactor your code to make it extractable, or add the doc comment /** @Ignore */ to this code element (in %s on line %d).', get_class($node->args[0]->value), $this->file, $node->args[0]->value->getLine());

                    if ($this->logger) {
                        $this->logger->error($message);
                        return;
                    }

                    throw new RuntimeException($message);
                }

                $domain = $node->args[$domain_index]->value->value;
            } else {
                $domain = 'messages';
            }

            $message = new Message($id, $domain);
            $message->setDesc($desc);
            $message->setMeaning($meaning);
            $message->addSource($this->fileSourceFactory->create($this->file, $node->getLine()));

            // `parameters` index
            $parameterIndex = $domain_index-1;
            if(isset($node->args[$parameterIndex])) {
                if($node->args[$parameterIndex]->value instanceof Node\Expr\Array_) {
                    foreach($node->args[$parameterIndex]->value->items as $item) {
                        $message->addPlaceholder($item->key->value);
                    }
                }
            }
        } elseif(strpos(strtolower($node->name), 'violation')!==false && count($node->args)>0) {
            $domain = 'validators';

            $idIndex = (strtolower($node->name) === 'addviolationat') ? 1 : 0;
            if (!$node->args[$idIndex]->value instanceof String_) {
                if ($ignore) {
                    return;
                }

                $message = sprintf('Can only extract the translation id from a scalar string, but got "%s". Please refactor your code to make it extractable, or add the doc comment /** @Ignore */ to this code element (in %s on line %d).', get_class($node->args[0]->value), $this->file, $node->args[0]->value->getLine());

                if ($this->logger) {
                    $this->logger->error($message);
                    return;
                }

                throw new RuntimeException($message);
            }

            $id = $node->args[$idIndex]->value->value;

            $message = new Message($id, $domain);
            $message->setDesc($desc);
            $message->setMeaning($meaning);
            $message->addSource($this->fileSourceFactory->create($this->file, $node->getLine()));

            // `parameters` index
            $index = $idIndex + 1;
            if(isset($node->args[$index])) {
                if($node->args[$index]->value instanceof Node\Expr\Array_) {
                    foreach($node->args[$index]->value->items as $item) {
                        $message->addPlaceholder($item->key->value);
                    }
                }
            }
        }

        $this->catalogue->add($message);
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
     * @param array $nodes
     * @return void
     */
    public function beforeTraverse(array $nodes)
    {
    }

    /**
     * @param Node $node
     * @return void
     */
    public function leaveNode(Node $node)
    {
    }

    /**
     * @param array $nodes
     * @return void
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
     * @param Node $node
     * @return null|string
     */
    protected function getDocCommentForNode(Node $node)
    {
        // check if there is a doc comment for the ID argument
        // ->trans(/** @Desc("FOO") */ 'my.id')
        if (null !== $comment = $node->args[0]->getDocComment()) {
            return $comment->getText();
        }

        // this may be placed somewhere up in the hierarchy,
        // -> /** @Desc("FOO") */ trans('my.id')
        // /** @Desc("FOO") */ ->trans('my.id')
        // /** @Desc("FOO") */ $translator->trans('my.id')
        if (null !== $comment = $node->getDocComment()) {
            return $comment->getText();
        } elseif (null !== $this->previousNode && $this->previousNode->getDocComment() !== null) {
            $comment = $this->previousNode->getDocComment();
            return is_object($comment) ? $comment->getText() : $comment;
        }

        return null;
    }
}
