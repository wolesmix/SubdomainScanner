#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Ccteam\Pss\SubdomainScanner;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Command\Command;

(new SingleCommandApplication())
    ->setName('Premium Server Scanner')
    ->setVersion('1.0.0-alpha')
    ->addArgument('domain', InputArgument::REQUIRED, 'Domain yang ingin di scan')
    ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Socket timeout, default 5 second.', 5)
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $scanner = new SubdomainScanner($input->getArgument('domain'));
        if ($subdomains = $scanner->scan()) {
            $totalVlun = 0;
            $totalOpen = 0;

            foreach($subdomains as $subdomain) {
                [$isOpenPort, $httpCode, $isVlun] = $subdomain->isOpen((int) $input->getOption('timeout'));

                if ($isOpenPort) {
                    $totalOpen++;

                    $output->write('<info>' . $subdomain->subdomain . '</info>');
                    $output->write('<info>::' . $subdomain->ip . '</info>');
                    $output->write('<info>::81</info>');

                    if ($isVlun) {
                        $totalVlun++;

                        $output->write('<info>::vlun::http(' . $httpCode . ')</info>');
                        $output->writeln('');

                        $filesUrl = $subdomain->getFilesUrl();
                        if (count($filesUrl) > 0) {
                            $output->writeln('<info>' . $subdomain->subdomain . '::Downloading files (' . count($filesUrl) . ' files found)....</info>');
                            foreach ($filesUrl as $url) {
                                $output->write('<info>Downloading ' . $subdomain->subdomain . '/' . $url[0] . '....</info>');
                                $subdomain->downloadFile($url);
                                $output->writeln('<info>Done</info>');
                            }
                        }
                    } else {
                        $output->writeln('<info>::http(' . $httpCode . ')</info>');
                    }
                } else {
                    $output->write('<fg=red>' . $subdomain->subdomain . '</>');
                    $output->write('<fg=red>::' . $subdomain->ip . '</>');
                    $output->writeln('');
                }
            }

            $output->writeln('<info>Total vlun: ' . $totalVlun . '</info>');
            $output->writeln('<info>Total open port: ' . $totalOpen . '</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<fg=red>Invalid domain name or no subdomain found.</>');
        return Command::FAILURE;
    })
    ->run();