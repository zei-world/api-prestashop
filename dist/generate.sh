#/bin/sh

if ! git diff-files --quiet --ignore-submodules --
then
    echo
    echo >&2 "/!\ Les éléments suivants doivent être validés :"
    echo
    git diff-files --name-status -r --ignore-submodules -- >&2
    exit 1
fi

echo Version actuelle : $(cat .version)

read -p "Entrez la nouvelle version : " version

sed -i 's/'"$(cat .version)"'/'"$version"'/' ../zei.php
sed -i 's/'"$(cat .version)"'/'"$version"'/' ../config.xml

echo $version > .version

git tag $version

rm ./zei-prestashop-latest.zip

mkdir zei

rsync -av --progress .. zei \
    --exclude dist \
    --exclude .git \
    --exclude .idea \
    --exclude .gitignore \
    --exclude README.md

zip -r ./zei-prestashop-latest.zip zei

rm -r zei
