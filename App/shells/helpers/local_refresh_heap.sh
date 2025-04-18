#!/usr/bin/env bash
#force refresh local branch `heap`

git checkout develop
git branch -D heap
git fetch
git checkout heap
