async function runAudioApiLessonExample() {
  const list = await BrailleStudioAPI.getAudioList({
    folder: 'speech',
    letters: 'a,b,k,l',
    maxlength: 4,
    limit: 10,
    sort: 'asc'
  });

  const randomItem = BrailleStudioAPI.pickRandom(list);
  if (!randomItem) return;

  await BrailleStudioAPI.playUrl(randomItem.url);
}
