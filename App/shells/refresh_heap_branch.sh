#!/usr/bin/env bash

git reset --hard
git checkout develop
git branch -D heap
git pull origin develop
git checkout -b heap
git push origin heap -f
